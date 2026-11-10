<?php

namespace App\Support;

/**
 * Checklists per vak. Een klus krijgt bij het eerste bezoek automatisch een
 * checklist: aanmelden en werkplek beschermen voorop, daarna de stappen van het
 * vak dat bij de klustitel past, en afsluiten met opruimen, eindfoto's en
 * afstemmen met de klant.
 *
 * Een stap is [titel, toelichting, foto verplicht]. De verplichte foto's zijn de
 * momenten die je later niet meer kunt aantonen: achter dichtgezette wanden,
 * waterdichting, metingen en valbeveiliging.
 */
class JobChecklistTemplates
{
    /** @var array<string, list<array{0: string, 1: string, 2: bool}>> */
    public const TRADES = [
        'sloop' => [
            ['Leidingen en elektra veilig afsluiten', 'Water dicht, groep uit, gas afgesloten waar nodig', true],
            ['Slopen volgens tekening', 'Alleen wat op de werkomschrijving staat', false],
            ['Asbestverdacht materiaal? Stop en meld', 'Twijfel is niet doorwerken, maak een melding', false],
            ['Afval scheiden en in container', 'Puin, hout en restafval apart', false],
        ],
        'tegel' => [
            ['Ondergrond controleren', 'Vlak, droog en draagkrachtig, maak een foto', true],
            ['Waterdichting en kitband aanbrengen', 'Hoeken en doorvoeren, foto verplicht voor de Wkb', true],
            ['Afschot vloer meten', 'Naar de goot, minimaal 1 procent, foto met waterpas', true],
            ['Tegels zetten volgens legplan', 'Startlijn en verdeling volgens tekening', false],
            ['Voegen en kitten', 'Sanitairkit in hoeken en bij aansluitingen', false],
        ],
        'elektra' => [
            ['Groep spanningsloos en gemeten', 'Foto van de meetwaarde', true],
            ['Kabels trekken en dozen plaatsen', 'Volgens installatietekening', false],
            ['Aardlek testen', 'Foto van de testwaarde', true],
            ['Meetrapport invullen', 'Isolatieweerstand en aardlek', false],
            ['Afmonteren schakelaars en wandcontactdozen', 'Recht en strak tegen de wand', false],
        ],
        'leiding' => [
            ['Leidingwerk aanleggen', 'Volgens tekening, beugels op afstand', false],
            ['Persproef 30 minuten', 'Foto van de manometer bij start en eind', true],
            ['Afvoer op afschot', 'Foto met waterpas', true],
            ['Douchegoot waterpas inbouwen', 'Hoogte afgestemd op tegelwerk, foto', true],
        ],
        'timmer' => [
            ['Maatvoering uitzetten', 'Volgens tekening, haaks en te lood', false],
            ['Regelwerk of metal stud plaatsen', 'Foto voordat je sluit: leidingen zichtbaar', true],
            ['Gipsplaat monteren', 'Naden versprongen, schroeven verzonken', false],
            ['Naden afwerken', 'Klaar voor tegelwerk of schilderwerk', false],
        ],
        'kozijn' => [
            ['Oud kozijn verwijderen', 'Stukken direct afvoeren', false],
            ['Kozijn waterpas plaatsen', 'Foto met waterpas', true],
            ['Aansluitingen luchtdicht afwerken', 'Folie en kit, foto voor de Wkb', true],
            ['Glas en beslag afstellen', 'Draai-kiep en sluiting controleren', false],
        ],
        'schilder' => [
            ['Ondergrond voorbereiden', 'Schuren, ontvetten, stofvrij', false],
            ['Plamuren en bijwerken', 'Gaten en naden', false],
            ['Grondlaag aanbrengen', '', false],
            ['Aflakken in twee lagen', 'Droogtijd tussen de lagen aanhouden', false],
        ],
        'stuc' => [
            ['Ondergrond voorstrijken', 'Zuigende delen eerst behandelen', false],
            ['Hoekprofielen stellen', 'Haaks en te lood', false],
            ['Stucwerk aanbrengen', 'In de juiste laagdikte', false],
            ['Afwerken en nameten', 'Vlakheid controleren, afwijkingen melden', true],
        ],
        'ruwbouw' => [
            ['Maatvoering controleren', 'Met tekening en peil', false],
            ['Wapening controleren voordat je stort', 'Foto verplicht voor de Wkb', true],
            ['Storten of metselen', 'Volgens bestek', false],
            ['Eindcontrole maat en peil', 'Afwijkingen melden', true],
        ],
        'dak' => [
            ['Valbeveiliging plaatsen', 'Foto van de randbeveiliging', true],
            ['Ondergrond droog en schoon', '', false],
            ['Isolatie aanbrengen', 'Naden sluiten', false],
            ['Dakbedekking aanbrengen', 'Overlappen en opstanden', true],
            ['Hemelwaterafvoer aansluiten', '', false],
        ],
        'bestrating' => [
            ['Uitzetten en afschot bepalen', 'Weg van de gevel', false],
            ['Zandbed afrijen en verdichten', '', false],
            ['Bestraten volgens patroon', '', false],
            ['Invegen en aftrillen', '', false],
        ],
        'toezicht' => [
            ['Rondgang over de bouwplaats', 'Veiligheid, orde en voortgang', false],
            ['Foto van de huidige situatie', 'Per ruimte of gevel', true],
            ['Bevindingen vastleggen', 'Afwijkingen als melding', false],
        ],
    ];

    /** Trefwoorden per vak, in volgorde van herkenning. */
    private const KEYWORDS = [
        'sloop' => '/sloop|strip/',
        'tegel' => '/tegel/',
        'elektra' => '/elektra|elektr|groepenkast/',
        'leiding' => '/afvoer|douchegoot|leiding|installatie|sanitair|loodgiet/',
        'kozijn' => '/kozijn|raam|deur/',
        'schilder' => '/schilder|verf|lakwerk/',
        'stuc' => '/stuc|stukadoor|spachtel/',
        'ruwbouw' => '/ruwbouw|fundering|metsel|beton/',
        'dak' => '/\bdak/',
        'bestrating' => '/bestrating|tuin|terras/',
        'timmer' => '/voorzet|plafond|timmer|wand|interieur|vloer/',
        'toezicht' => '/toezicht|inventarisatie|opname/',
    ];

    /** De vaste stappen waarmee elke klus begint. */
    private const OPENING = [
        ['Aanmelden bij klant of bouwplaats', 'Stel je voor en vraag naar bijzonderheden', false],
        ['Werkplek beschermen', 'Vloeren en looproute afdekken, maak een foto', true],
    ];

    /** En waarmee elke klus eindigt. */
    private const CLOSING = [
        ['Opruimen en afval afvoeren', 'Werkplek bezemschoon achterlaten', false],
        ['Eindfotos maken', 'Overzicht van het resultaat', true],
        ['Kort afstemmen met de klant', 'Laat zien wat er gedaan is', false],
    ];

    /**
     * De checklist voor een klus: openingsstappen, de twee best passende vakken
     * en de afsluiting. Zonder match pakken we toezicht, zodat er altijd een
     * zinnige rondgang met foto's in staat.
     *
     * @return list<array{title: string, hint: string, photo_required: bool}>
     */
    public static function for(string $title, ?string $projectType = null): array
    {
        $haystack = mb_strtolower(trim($title.' '.$projectType));
        $trades = [];

        foreach (self::KEYWORDS as $trade => $pattern) {
            if (preg_match($pattern, $haystack) === 1) {
                $trades[] = $trade;
            }
        }

        if ($trades === []) {
            $trades = [str_contains($haystack, 'badkamer') ? 'timmer' : 'toezicht'];
        }

        $steps = self::OPENING;

        foreach (array_slice($trades, 0, 2) as $trade) {
            $steps = [...$steps, ...self::TRADES[$trade]];
        }

        $steps = [...$steps, ...self::CLOSING];

        return array_map(fn (array $step) => [
            'title' => $step[0],
            'hint' => $step[1],
            'photo_required' => $step[2],
        ], $steps);
    }

    /**
     * De vijf vragen van de Laatste Minuut Risico Analyse. Allemaal ja, anders
     * begint de klus niet.
     *
     * @return list<string>
     */
    public static function lmra(): array
    {
        return [
            'Ik weet wat ik moet doen en hoe',
            'Gereedschap en persoonlijke beschermingsmiddelen zijn in orde',
            'De werkplek is veilig (stroom, gas, water, valgevaar)',
            'Ik weet wie ik bel bij nood',
            'Geen onbekende risicos (asbest, instabiele constructie)',
        ];
    }
}
