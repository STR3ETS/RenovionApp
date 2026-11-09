<?php

namespace App\Actions;

use App\Enums\ActionSource;
use App\Enums\MeetingStatus;
use App\Enums\TimelineEventType;
use App\Models\Meeting;
use App\Services\MeetingReporter;
use App\Services\Transcription\Transcriber;
use App\Services\Transcription\TranscriptionFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Eén stap verder met een gespreksverslag: insturen bij de transcriptiedienst, transcript ophalen, en
 * daarna laat Nova er een verslag van maken. Draait elke minuut (meetings:process) en ook als iemand het
 * verslag openheeft, zodat het zonder wachtrij werkt. Eén tegelijk per verslag (cache-lock).
 */
class ProcessMeeting
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(private Transcriber $transcriber, private MeetingReporter $reporter) {}

    public function handle(Meeting $meeting): void
    {
        // Na een mislukte poging even wachten: het verslag vraagt elke 5 s, en anders zijn de pogingen bij een
        // korte storing al binnen een halve minuut op.
        if ($meeting->status !== MeetingStatus::Verwerken || Cache::has(self::backoffKey($meeting))) {
            return;
        }

        // Ruim genomen: Whisper zet een lange opname in één keer om, dat kan een paar minuten duren.
        Cache::lock('meeting-process:'.$meeting->id, 600)->get(fn () => $this->step($meeting->fresh()));
    }

    private function step(Meeting $meeting): void
    {
        if ($meeting->status !== MeetingStatus::Verwerken) {
            return;
        }

        try {
            if ($meeting->transcript === null) {
                if (! $this->transcriber->isConfigured()) {
                    return; // wacht tot er een sleutel is; het verslag zegt dat
                }
                if (! $meeting->hasAudio()) {
                    $this->fail($meeting, 'Er is geen geluid opgenomen.');

                    return;
                }
                if ($meeting->provider_id === null) {
                    $meeting->update(['provider' => class_basename($this->transcriber), 'provider_id' => $this->transcriber->submit($meeting)]);
                }
                $transcript = $this->transcriber->fetch($meeting);
                if ($transcript === null) {
                    return; // nog bezig bij de dienst
                }
                if ($transcript === []) {
                    $this->fail($meeting, 'Er was geen spraak te horen in de opname.');

                    return;
                }
                $meeting->update(['transcript' => $transcript]);
            }

            if (! $this->reporter->isConfigured()) {
                return;
            }

            $report = $this->reporter->report($meeting);
            $meeting->update([
                'summary' => $report['summary'],
                'decisions' => $report['decisions'],
                'actions' => $report['actions'],
                'questions' => $report['questions'],
                'speakers' => $report['speakers'],
                'status' => MeetingStatus::Klaar,
                'error' => null,
            ]);

            $meeting->project?->customer->recordEvent(
                TimelineEventType::VoiceMemo,
                'Gespreksverslag: '.$meeting->title,
                $report['summary'] ?: null,
                $meeting,
                ActionSource::Nova,
            );
        } catch (TranscriptionFailed $exception) {
            $this->fail($meeting, 'De transcriptiedienst kon de opname niet verwerken: '.$exception->getMessage());
        } catch (Throwable $exception) {
            // Tijdelijke storing (netwerk, dienst): een paar keer opnieuw, daarna als mislukt tonen.
            Log::warning('Gespreksverslag '.$meeting->id.' verwerken lukte niet', ['error' => $exception->getMessage()]);
            $meeting->increment('attempts');
            if ($meeting->attempts >= self::MAX_ATTEMPTS) {
                $this->fail($meeting, 'Verwerken lukte niet na een paar pogingen. Probeer het opnieuw.');
            } else {
                Cache::put(self::backoffKey($meeting), true, now()->addSeconds(min(300, 30 * $meeting->attempts)));
            }
        }
    }

    /** "Opnieuw proberen" wacht niet op de pauze van de vorige mislukte poging. */
    public static function clearBackoff(Meeting $meeting): void
    {
        Cache::forget(self::backoffKey($meeting));
    }

    private static function backoffKey(Meeting $meeting): string
    {
        return 'meeting-backoff:'.$meeting->id;
    }

    private function fail(Meeting $meeting, string $message): void
    {
        $meeting->update(['status' => MeetingStatus::Mislukt, 'error' => $message]);
    }
}
