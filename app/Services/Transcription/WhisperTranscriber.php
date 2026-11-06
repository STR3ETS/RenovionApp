<?php

namespace App\Services\Transcription;

use App\Models\Meeting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * OpenAI Whisper (zoals in Eazyonline2026): de hele opname in één keer, Nederlands. Whisper antwoordt
 * meteen, dus submit() doet niets en fetch() zet om. Sprekers houdt Whisper niet uit elkaar; de stukken
 * komen terug met tijden, zonder spreker.
 */
class WhisperTranscriber implements Transcriber
{
    /** Grens van de Whisper-API per bestand. */
    private const MAX_BYTES = 25 * 1024 * 1024;

    public function isConfigured(): bool
    {
        return filled(config('renovion.transcription.openai_key'));
    }

    public function submit(Meeting $meeting): string
    {
        return 'whisper-'.$meeting->id;
    }

    public function fetch(Meeting $meeting): ?array
    {
        if ($meeting->audio_bytes > self::MAX_BYTES) {
            throw new TranscriptionFailed('de opname is groter dan 25 MB, de grens van Whisper. Neem lange gesprekken in delen op.');
        }

        // Een lange opname kan een paar minuten duren; niet afbreken halverwege.
        set_time_limit(600);

        $extension = pathinfo($meeting->audio_path, PATHINFO_EXTENSION) ?: 'webm';
        $response = Http::withToken((string) config('renovion.transcription.openai_key'))
            ->timeout(600)
            ->attach('file', Storage::get($meeting->audio_path), 'opname.'.$extension)
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => (string) config('renovion.transcription.openai_model'),
                'language' => 'nl',
                'response_format' => 'verbose_json',
            ]);

        if ($response->clientError()) {
            throw new TranscriptionFailed((string) ($response->json('error.message') ?? 'de opname werd geweigerd.'));
        }
        $response->throw();

        return collect($response->json('segments') ?? [])
            ->map(fn (array $segment) => [
                'speaker' => '',
                'start' => (int) round(((float) ($segment['start'] ?? 0)) * 1000),
                'end' => (int) round(((float) ($segment['end'] ?? 0)) * 1000),
                'text' => trim((string) ($segment['text'] ?? '')),
            ])
            ->reject(fn (array $segment) => $segment['text'] === '' || self::isSilenceNoise($segment['text']))
            ->values()
            ->all();
    }

    /**
     * Whisper verzint bij stilte soms een ondertitelregel, zoals "Ondertiteling door de Amara.org
     * gemeenschap" (zelfde filter als in Eazyonline2026).
     */
    public static function isSilenceNoise(string $text): bool
    {
        return mb_strlen($text) < 120 && preg_match('/amara\.org|ondertitel|bedankt voor het kijken|tv gelderland/i', $text) === 1;
    }
}
