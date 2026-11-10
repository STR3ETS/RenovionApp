<?php

namespace App\Support;

/**
 * De hoofdstuknummering die Renovion in de begrotingstool gebruikt, met voor elk
 * projecttype een startskelet. Een nieuwe begroting begint dus niet leeg maar
 * met de hoofdstukken die bij dit soort werk horen, in de juiste volgorde.
 *
 * Het nummer is leidend: elke begroting van Renovion gebruikt dezelfde codes,
 * zodat nacalculaties van verschillende projecten vergelijkbaar blijven.
 */
class BudgetChapterLibrary
{
    /** @var array<string, string> */
    public const CHAPTERS = [
        '01' => 'Werkvoorbereiding en engineering',
        '05' => 'Bouwplaatsvoorzieningen',
        '10' => 'Sloop- en demontagewerk',
        '12' => 'Grondwerk',
        '14' => 'Buitenriolering en drainage',
        '21' => 'Betonwerk',
        '22' => 'Metselwerk',
        '23' => 'Vooraf vervaardigde steenachtige elementen',
        '24' => 'Ruwbouwtimmerwerk',
        '25' => 'Staalwerk',
        '30' => 'Kozijnen, ramen en deuren',
        '31' => 'Systeemwanden en plafonds',
        '32' => 'Trappen, hekken en balustrades',
        '33' => 'Dakbedekkingen',
        '35' => 'Natuur- en kunststeen',
        '39' => 'Diversen',
        '40' => 'Stucadoorswerk',
        '41' => 'Tegelwerk',
        '42' => 'Dekvloeren en vloersystemen',
        '45' => 'Afbouwtimmerwerk',
        '46' => 'Schilderwerk',
        '50' => 'Installaties algemeen',
        '51' => 'Riolering',
        '52' => 'Waterinstallaties',
        '60' => 'Verwarmingsinstallatie',
        '61' => 'Mechanische ventilatie',
        '70' => 'Elektrotechnische installatie',
        '71' => 'Liftinstallatie',
        '73' => 'Keukens',
        '74' => 'Badkamers en toiletten',
        '76' => 'Zonnepanelen en groendak',
        '90' => 'Algemene bouwplaatskosten',
    ];

    /**
     * Startskelet per soort werk: de hoofdstukken met per hoofdstuk een eerste
     * paragraaf, zodat de calculator direct regels kan typen.
     *
     * @var array<string, array<string, list<string>>>
     */
    private const TEMPLATES = [
        'badkamer' => [
            '10' => ['Sloopwerk badkamer'],
            '51' => ['Afvoeren aanpassen'],
            '52' => ['Leidingwerk warm en koud'],
            '61' => ['Ventilatie'],
            '70' => ['Elektra badkamer'],
            '31' => ['Voorzetwanden en plafond'],
            '42' => ['Afschotvloer en waterdichting'],
            '41' => ['Wand- en vloertegels'],
            '74' => ['Sanitair plaatsen'],
            '05' => ['Afvoer bouwafval'],
        ],
        'keuken' => [
            '10' => ['Sloopwerk keuken'],
            '51' => ['Afvoeren aanpassen'],
            '52' => ['Leidingwerk warm en koud'],
            '70' => ['Elektra en groepen'],
            '40' => ['Wanden herstellen'],
            '41' => ['Vloer en achterwand'],
            '73' => ['Keuken monteren'],
            '46' => ['Schilderwerk'],
        ],
        'stucwerk' => [
            '05' => ['Afdekken en beschermen'],
            '40' => ['Voorbehandeling', 'Stucwerk wanden', 'Stucwerk plafonds'],
            '46' => ['Schilderwerk'],
        ],
        'schilderwerk' => [
            '05' => ['Afdekken en beschermen'],
            '46' => ['Voorbehandeling', 'Binnenschilderwerk', 'Buitenschilderwerk'],
        ],
        'tegelwerk' => [
            '05' => ['Afdekken en beschermen'],
            '42' => ['Ondergrond en waterdichting'],
            '41' => ['Vloertegels', 'Wandtegels', 'Voegen en kitten'],
        ],
        'aanbouw' => [
            '01' => ['Engineering en vergunning'],
            '05' => ['Bouwplaatsinrichting'],
            '10' => ['Sloopwerk'],
            '12' => ['Grondwerk'],
            '21' => ['Fundering en vloer'],
            '22' => ['Metselwerk'],
            '24' => ['Ruwbouwtimmerwerk'],
            '30' => ['Kozijnen en beglazing'],
            '33' => ['Dakbedekking'],
            '42' => ['Dekvloer'],
            '40' => ['Stucwerk'],
            '70' => ['Elektra'],
            '60' => ['Verwarming'],
            '90' => ['Algemene bouwplaatskosten'],
        ],
        'renovatie' => [
            '01' => ['Werkvoorbereiding'],
            '05' => ['Bouwplaatsvoorzieningen'],
            '10' => ['Sloop- en demontagewerk'],
            '31' => ['Wanden en plafonds'],
            '42' => ['Vloeren'],
            '40' => ['Stucwerk'],
            '41' => ['Tegelwerk'],
            '46' => ['Schilderwerk'],
            '70' => ['Elektra'],
            '52' => ['Waterinstallatie'],
            '90' => ['Algemene bouwplaatskosten'],
        ],
    ];

    /** Trefwoorden per sjabloon, in volgorde van herkenning. */
    private const KEYWORDS = [
        'badkamer' => '/badkamer|sanitair|toilet/',
        'keuken' => '/keuken/',
        'tegelwerk' => '/tegel/',
        'stucwerk' => '/stuc|spachtel/',
        'schilderwerk' => '/schilder|verf|lak/',
        'aanbouw' => '/aanbouw|uitbouw|nieuwbouw|dakkapel|opbouw/',
    ];

    /**
     * Het skelet dat bij dit werk past. Zonder match pakken we het brede
     * renovatiesjabloon, want dat dekt het meeste werk van Renovion.
     *
     * @return array<string, list<string>>
     */
    public static function for(?string $projectType, ?string $name = null): array
    {
        $haystack = mb_strtolower(trim($projectType.' '.$name));

        foreach (self::KEYWORDS as $template => $pattern) {
            if (preg_match($pattern, $haystack) === 1) {
                return self::TEMPLATES[$template];
            }
        }

        return self::TEMPLATES['renovatie'];
    }

    /**
     * @return list<string>
     */
    public static function templateNames(): array
    {
        return array_keys(self::TEMPLATES);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function template(string $name): array
    {
        return self::TEMPLATES[$name] ?? self::TEMPLATES['renovatie'];
    }

    /**
     * Het betaalschema waarmee Renovion standaard werkt: een aanbetaling bij
     * start, termijnen op mijlpalen en vijf procent bij oplevering. Die laatste
     * vijf procent is de wettelijke opschortingsregeling.
     *
     * @return list<array{description: string, percentage: float}>
     */
    public static function defaultTerms(): array
    {
        return [
            ['description' => 'Bij opdracht, aanbetaling', 'percentage' => 20],
            ['description' => 'Start werkzaamheden', 'percentage' => 25],
            ['description' => 'Ruwbouw gereed', 'percentage' => 25],
            ['description' => 'Afbouw gereed', 'percentage' => 25],
            ['description' => 'Bij oplevering, opschortingsrecht', 'percentage' => 5],
        ];
    }
}
