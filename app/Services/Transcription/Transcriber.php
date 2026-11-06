<?php

namespace App\Services\Transcription;

use App\Models\Meeting;

/**
 * Spraak naar tekst voor gespreksverslagen: eerst insturen, daarna ophalen tot het klaar is. Een dienst
 * die meteen antwoordt (Whisper) doet het werk in fetch().
 */
interface Transcriber
{
    /** Kan deze dienst nu werken (sleutel aanwezig)? */
    public function isConfigured(): bool;

    /** Stuurt de opname in; geeft het id bij de dienst terug (komt in provider_id). */
    public function submit(Meeting $meeting): string;

    /**
     * Haalt het transcript op: null zolang het nog bezig is, anders de uitspraken op volgorde. Een lege
     * spreker betekent dat de dienst sprekers niet uit elkaar houdt.
     *
     * @return list<array{speaker: string, start: int, end: int, text: string}>|null
     *
     * @throws TranscriptionFailed
     */
    public function fetch(Meeting $meeting): ?array;
}
