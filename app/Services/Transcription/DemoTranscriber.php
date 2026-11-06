<?php

namespace App\Services\Transcription;

use App\Models\Meeting;

/**
 * Vast voorbeeldgesprek, alleen voor lokaal testen en de testsuite (TRANSCRIPTION_DRIVER=demo). Wat je
 * inspreekt wordt hiermee niet omgezet; het verslag laat zien hoe de rest van de stroom werkt.
 */
class DemoTranscriber implements Transcriber
{
    public function isConfigured(): bool
    {
        return true;
    }

    public function submit(Meeting $meeting): string
    {
        return 'demo-'.$meeting->id;
    }

    public function fetch(Meeting $meeting): ?array
    {
        return [
            ['speaker' => 'A', 'start' => 0, 'end' => 5200, 'text' => 'Goedemorgen, we lopen de badkamer en de planning even door.'],
            ['speaker' => 'B', 'start' => 5400, 'end' => 11800, 'text' => 'We twijfelen nog tussen een inloopdouche en het bad houden.'],
            ['speaker' => 'A', 'start' => 12000, 'end' => 19500, 'text' => 'Met deze maten past een inloopdouche van 120 centimeter goed, dan blijft er ruimte voor een dubbel meubel.'],
            ['speaker' => 'B', 'start' => 19800, 'end' => 23000, 'text' => 'Dan kiezen we voor de inloopdouche.'],
            ['speaker' => 'A', 'start' => 23200, 'end' => 30500, 'text' => 'Ik pas de begroting aan en stuur u voor vrijdag een nieuwe versie. Kan de spiegelkast eigenlijk 140 breed?'],
            ['speaker' => 'B', 'start' => 30800, 'end' => 34000, 'text' => 'Dat weet ik nog niet, dat laat ik u weten.'],
        ];
    }
}
