<?php

namespace App\Support;

/**
 * Controlepunten per soort werk, en de acht stappen van een oplevering.
 *
 * De controlepunten zijn de dingen die je achteraf niet meer kunt vaststellen:
 * of de waterdichting goed zit, of het afschot gemeten is, of de aardlek getest
 * is. Daarom staan ze apart van de werkbon.
 */
class QualityTemplates
{
    /** @var array<string, list<string>> */
    public const CHECKS = [
        'badkamer' => [
            'Waterdichting gekit en gecoat',
            'Afschot douchevloer gemeten',
            'Persproef leidingen uitgevoerd',
            'Aardlek getest en meetrapport ingevuld',
            'Ventilatie werkt en is gemeten',
            'Tegelwerk vlak, voegen gelijk',
            'Sanitair afgesteld en lekdicht',
        ],
        'keuken' => [
            'Constructieve aanpassing volgens berekening',
            'Aansluitingen water en afvoer lekdicht',
            'Elektra groepen en aardlek getest',
            'Afzuigkap afvoer naar buiten',
            'Werkblad waterpas en gekit',
        ],
        'aanbouw' => [
            'Fundering conform tekening, met foto',
            'Wapening gecontroleerd voordat er gestort werd',
            'Isolatiewaarden conform de Rc uit het bestek',
            'Luchtdichting aansluitingen',
            'Brandwerendheid doorvoeren',
            'Dakbedekking en hemelwaterafvoer',
            'Installaties gekeurd',
        ],
        'stucwerk' => [
            'Ondergrond voorbehandeld',
            'Vlakheid binnen de tolerantie gemeten',
            'Hoeken haaks en te lood',
            'Geen krimpscheuren na droging',
        ],
        'renovatie' => [
            'Werkplek veilig en schoon opgeleverd',
            'Maatvoering gecontroleerd',
            'Afwerking volgens offerte',
            'Installaties getest',
        ],
    ];

    /** Extra stukken bij een project onder de Wet kwaliteitsborging. */
    public const WKB_CHECKS = [
        'Fotos van de kritische momenten vastgelegd',
        'Borgingsplan van de kwaliteitsborger in het dossier',
        'Consumentendossier samengesteld',
        'Gereedmelding bij de gemeente gedaan',
    ];

    /**
     * De acht stappen van een oplevering, met per stap de voorwaarde waar het
     * op vastloopt als het niet klopt.
     *
     * @var list<array{0: string, 1: string}>
     */
    public const FLOW = [
        ['Werkzaamheden afgerond', 'Alle werkbonnen goedgekeurd en voortgang minstens 95 procent'],
        ['Interne kwaliteitscontrole', 'Alle controlepunten afgevinkt'],
        ['Restpuntenlijst', 'Restpunten vastgelegd met locatie'],
        ['Herstelacties', 'Alle restpunten hersteld'],
        ['Klantinspectie', 'Samen met de klant gecontroleerd'],
        ['Ondertekening', 'Opleverformulier getekend'],
        ['Opleverdocumenten', 'Opleverrapport en garantie in het dossier'],
        ['Garanties en nazorg', 'Project naar nazorg'],
    ];

    /** Trefwoorden per template, in volgorde van herkenning. */
    private const KEYWORDS = [
        'badkamer' => '/badkamer|sanitair/',
        'keuken' => '/keuken/',
        'aanbouw' => '/aanbouw|uitbouw|nieuwbouw|dakkapel|opbouw/',
        'stucwerk' => '/stuc|spachtel/',
    ];

    /** Projecten onder de Wkb: constructief werk aan de buitenkant. */
    public static function fallsUnderWkb(?string $projectType): bool
    {
        return preg_match('/aanbouw|uitbouw|nieuwbouw|dakkapel|opbouw|constructie/', mb_strtolower((string) $projectType)) === 1;
    }

    public static function templateFor(?string $projectType): string
    {
        $haystack = mb_strtolower((string) $projectType);

        foreach (self::KEYWORDS as $template => $pattern) {
            if (preg_match($pattern, $haystack) === 1) {
                return $template;
            }
        }

        return 'renovatie';
    }

    /**
     * @return list<string>
     */
    public static function checksFor(string $template): array
    {
        return self::CHECKS[$template] ?? self::CHECKS['renovatie'];
    }

    /**
     * De garantietermijn in maanden die Renovion standaard geeft, per soort werk.
     * Constructief werk krijgt langer dan afwerking.
     */
    public static function warrantyMonths(?string $projectType): int
    {
        return match (self::templateFor($projectType)) {
            'aanbouw' => 120,
            'badkamer', 'keuken' => 24,
            default => 12,
        };
    }
}
