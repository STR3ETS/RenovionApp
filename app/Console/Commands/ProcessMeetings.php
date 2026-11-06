<?php

namespace App\Console\Commands;

use App\Actions\ProcessMeeting;
use App\Enums\MeetingStatus;
use App\Models\Meeting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ProcessMeetings extends Command
{
    protected $signature = 'meetings:process';

    protected $description = 'Gespreksverslagen verder verwerken (transcriptie, verslag door Nova) en oude opnames opruimen (AVG)';

    public function handle(ProcessMeeting $process): int
    {
        Meeting::where('status', MeetingStatus::Verwerken)->oldest()->limit(20)->get()
            ->each(fn (Meeting $meeting) => $process->handle($meeting));

        // Een opname die al een dag niet meer binnenkwam (app gesloten, telefoon leeg): toch verwerken.
        Meeting::where('status', MeetingStatus::Opname)->where('updated_at', '<', now()->subDay())->get()
            ->each(fn (Meeting $meeting) => $meeting->update(['status' => MeetingStatus::Verwerken, 'ended_at' => $meeting->updated_at]));

        // Bewaartermijn: het geluid gaat weg, het verslag en transcript blijven.
        $days = (int) config('renovion.transcription.audio_retention_days');
        Meeting::whereNotNull('audio_path')->whereNull('audio_deleted_at')->whereIn('status', [MeetingStatus::Klaar, MeetingStatus::Mislukt])
            ->where('ended_at', '<', now()->subDays($days))->get()
            ->each(function (Meeting $meeting) {
                Storage::delete($meeting->audio_path);
                $meeting->update(['audio_deleted_at' => now()]);
            });

        return self::SUCCESS;
    }
}
