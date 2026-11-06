<?php

namespace App\Services\Transcription;

use App\Models\Meeting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * AssemblyAI via het EU-adres (api.eu.assemblyai.com): audio en transcript blijven in de EU. Sprekers
 * worden gescheiden (speaker_labels), de taal herkent de dienst zelf met Nederlands als verwachting.
 */
class AssemblyAiTranscriber implements Transcriber
{
    public function isConfigured(): bool
    {
        return filled(config('renovion.transcription.api_key'));
    }

    public function submit(Meeting $meeting): string
    {
        // Het bestand als ruwe bytes; een JSON-body geeft later "Transcoding failed".
        $upload = $this->client()
            ->withBody(Storage::readStream($meeting->audio_path), 'application/octet-stream')
            ->timeout(300)
            ->post('/v2/upload')
            ->throw()
            ->json('upload_url');

        return (string) $this->client()->post('/v2/transcript', [
            'audio_url' => $upload,
            'speech_models' => array_values(config('renovion.transcription.speech_models')),
            'language_detection' => true,
            'language_detection_options' => ['expected_languages' => ['nl', 'en'], 'fallback_language' => 'nl'],
            'speaker_labels' => true,
        ])->throw()->json('id');
    }

    public function fetch(Meeting $meeting): ?array
    {
        $transcript = $this->client()->get('/v2/transcript/'.$meeting->provider_id)->throw()->json();

        return match ($transcript['status'] ?? null) {
            'completed' => collect($transcript['utterances'] ?? [])
                ->map(fn (array $utterance) => [
                    'speaker' => (string) ($utterance['speaker'] ?? 'A'),
                    'start' => (int) ($utterance['start'] ?? 0),
                    'end' => (int) ($utterance['end'] ?? 0),
                    'text' => trim((string) ($utterance['text'] ?? '')),
                ])
                ->filter(fn (array $utterance) => $utterance['text'] !== '')
                ->values()
                ->all(),
            'error' => throw new TranscriptionFailed((string) ($transcript['error'] ?? 'Onbekende fout bij de transcriptiedienst.')),
            default => null,
        };
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('renovion.transcription.base_url'))
            ->withHeaders(['authorization' => (string) config('renovion.transcription.api_key')])
            ->acceptJson()
            ->timeout(30);
    }
}
