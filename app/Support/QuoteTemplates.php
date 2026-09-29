<?php

namespace App\Support;

use App\Models\Quote;

/**
 * Voorgeconditioneerde Renovion-offertes per type werk (briefing §6).
 * De vaste blokvolgorde komt uit de briefing; templates vullen de blokken
 * met standaardteksten die Imad vóór verzending altijd kan aanpassen.
 */
class QuoteTemplates
{
    /**
     * Vaste blokken in briefing-volgorde: key => titel.
     *
     * @var array<string, string>
     */
    public const BLOCKS = [
        'samenvatting' => 'Samenvatting',
        'scope' => 'Scope van het werk',
        'uitgangspunten' => 'Uitgangspunten',
        'werkzaamheden' => 'Werkzaamheden',
        'fasering' => 'Fasering',
        'planning' => 'Planning',
        'investering' => 'Investering',
        'stelposten' => 'Stelposten',
        'meerwerk' => 'Meerwerk',
        'uitsluitingen' => 'Uitsluitingen',
        'garanties' => 'Garanties',
        'voorwaarden' => 'Voorwaarden',
        'betaaltermijnen' => 'Betaaltermijnen',
        'faq' => 'Veelgestelde vragen',
    ];

    /**
     * @return array<string, string> template-key => label
     */
    public static function options(): array
    {
        return [
            'renovatie' => 'Renovatie',
            'verbouwing' => 'Verbouwing woning',
            'aanbouw' => 'Aanbouw',
            'badkamer' => 'Badkamerrenovatie',
            'onderhoud' => 'Onderhoud',
            'utiliteit' => 'Utiliteit / zakelijk',
        ];
    }

    /**
     * Gok het best passende template op basis van de dienstomschrijving.
     */
    public static function guess(?string $service): string
    {
        $service = mb_strtolower((string) $service);

        return match (true) {
            str_contains($service, 'badkamer') || str_contains($service, 'toilet') => 'badkamer',
            str_contains($service, 'aanbouw') || str_contains($service, 'uitbouw') => 'aanbouw',
            str_contains($service, 'verbouwing') => 'verbouwing',
            str_contains($service, 'onderhoud') || str_contains($service, 'schilder') => 'onderhoud',
            str_contains($service, 'utilit') || str_contains($service, 'bedrijf') || str_contains($service, 'kantoor') => 'utiliteit',
            default => 'renovatie',
        };
    }

    /**
     * Bouw de blokkenlijst voor een offerte: [{key, title, body, enabled}].
     * Zonder offerte blijven de {klant}/{plaats}-tokens staan (voor templates).
     *
     * @return list<array{key: string, title: string, body: string, enabled: bool}>
     */
    public static function blocks(string $template, ?Quote $quote = null): array
    {
        $texts = array_merge(self::sharedTexts(), self::templateTexts($template));

        $replacements = [
            '{werk}' => self::options()[$template] ?? 'Renovatie',
        ];

        if ($quote !== null) {
            $replacements['{klant}'] = $quote->customer?->name ?? 'de opdrachtgever';
            $replacements['{plaats}'] = $quote->customer?->city ?? 'de projectlocatie';
        }

        return collect(self::BLOCKS)
            ->map(fn (string $title, string $key) => [
                'key' => $key,
                'title' => $title,
                'body' => strtr($texts[$key] ?? '', $replacements),
                'enabled' => true,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function sharedTexts(): array
    {
        return [
            'uitgangspunten' => "- De werklocatie is tijdens werkdagen (07:00–17:00) bereikbaar en beschikbaar.\n- Water en elektra zijn op de locatie aanwezig en kosteloos te gebruiken.\n- De genoemde prijzen zijn gebaseerd op de huidige, zichtbare situatie; verborgen gebreken vallen buiten deze offerte.\n- Vergunningen (indien nodig) worden door de opdrachtgever aangevraagd, tenzij anders afgesproken.",
            'fasering' => 'Het werk verloopt volgens de vaste Renovion-fasering: voorbereiding en werkvoorbereiding, inkoop en planning, uitvoering, controle en oplevering. U ziet de voortgang per fase terug in uw klantomgeving.',
            'planning' => 'Na akkoord plannen we het werk in overleg met u in. U ontvangt vooraf een duidelijke weekplanning; bij wijzigingen informeren we u direct.',
            'investering' => 'Onderstaande investering is opgebouwd uit heldere posten. Alle bedragen zijn inclusief arbeid, materiaal en coördinatie, tenzij anders vermeld.',
            'stelposten' => 'Voor onderdelen waar u zelf nog een keuze in maakt, werken we met stelposten. Een stelpost wordt achteraf verrekend op basis van de werkelijke keuze en aankoopprijs.',
            'meerwerk' => 'Meerwerk voeren we uitsluitend uit na uw schriftelijke of digitale goedkeuring, met vooraf een prijsopgave. Zo komt u nooit voor verrassingen te staan.',
            'uitsluitingen' => "- Herstel van verborgen gebreken (bijv. verrot houtwerk of asbest) die pas tijdens het werk zichtbaar worden.\n- Sauswerk en behang, tenzij expliciet opgenomen.\n- Verhuizen of opslaan van inboedel.",
            'garanties' => 'Renovion staat voor kwaliteit: geen half werk en vage beloftes. Op ons werk geldt een garantie van 5 jaar op de uitvoering; op materialen gelden de fabrieksgaranties.',
            'voorwaarden' => 'Op deze offerte zijn onze algemene voorwaarden van toepassing (op aanvraag beschikbaar). Deze offerte is geldig tot de vermelde datum. Prijswijzigingen van leveranciers na die datum kunnen worden doorberekend.',
            'betaaltermijnen' => "- 30% bij opdracht (aanbetaling)\n- 40% bij start van de uitvoering\n- 25% tijdens de uitvoering\n- 5% bij oplevering, na uw akkoord",
            'faq' => "Hoe lang duurt het werk? De doorlooptijd stemmen we na akkoord met u af in de planning.\nWie is mijn aanspreekpunt? U krijgt één vast aanspreekpunt en volgt alles in uw klantomgeving.\nWat als ik iets wil wijzigen? Kleine wijzigingen overleggen we direct; grotere wijzigingen ontvangt u als meerwerkvoorstel.",
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function templateTexts(string $template): array
    {
        return match ($template) {
            'badkamer' => [
                'samenvatting' => "Beste {klant},\n\nBedankt voor uw aanvraag. Hierbij ontvangt u onze offerte voor de complete renovatie van uw badkamer in {plaats}. We nemen u van sloop tot oplevering volledig uit handen, met één aanspreekpunt en een strakke planning.",
                'scope' => 'Complete badkamerrenovatie: slopen en afvoeren van de bestaande badkamer, aanpassen van leiding- en elektrawerk, waterdicht afwerken, tegelwerk, stucwerk en montage van het sanitair.',
                'werkzaamheden' => "- Bestaande badkamer strippen en afvoeren\n- Leidingwerk (water en afvoer) aanpassen\n- Elektra: aarding, spots en ventilatie\n- Wanden en vloer waterdicht voorbereiden\n- Tegelwerk vloer en wanden\n- Plafond stuken en afwerken\n- Sanitair monteren en aansluiten\n- Kitwerk en eindschoonmaak",
            ],
            'aanbouw' => [
                'samenvatting' => "Beste {klant},\n\nBedankt voor uw aanvraag. Hierbij onze offerte voor de aanbouw aan uw woning in {plaats}. Van fundering tot afwerking regelen wij het volledige traject, inclusief afstemming met constructeur en leveranciers.",
                'scope' => 'Realisatie van de aanbouw: fundering, ruwbouw, dak, kozijnen, installaties en de complete binnenafwerking, aansluitend op de bestaande woning.',
                'werkzaamheden' => "- Grondwerk en fundering\n- Ruwbouw: wanden, vloer en dakconstructie\n- Dakbedekking en waterdichting\n- Kozijnen en beglazing\n- Doorbraak en koppeling met de bestaande woning\n- Installaties: elektra, verwarming en eventueel water\n- Afwerking: stucwerk, vloeren en schilderwerk",
            ],
            'verbouwing' => [
                'samenvatting' => "Beste {klant},\n\nBedankt voor uw aanvraag. Hierbij ontvangt u onze offerte voor de verbouwing van uw woning in {plaats}. Wij coördineren alle disciplines, zodat u één aanspreekpunt heeft van start tot oplevering.",
                'scope' => 'Verbouwing van de woning volgens de besproken wensen, inclusief coördinatie van alle vakmensen, installaties en afwerking.',
                'werkzaamheden' => "- Sloop- en breekwerk volgens plan\n- Constructieve aanpassingen\n- Leidingwerk en elektra aanpassen\n- Wanden, plafonds en vloeren afwerken\n- Stuc-, tegel- en schilderwerk\n- Eindcontrole en oplevering",
            ],
            'onderhoud' => [
                'samenvatting' => "Beste {klant},\n\nBedankt voor uw aanvraag. Hierbij onze offerte voor de onderhoudswerkzaamheden aan uw woning in {plaats}. Goed onderhoud voorkomt grotere kosten later — we werken netjes, snel en met oog voor detail.",
                'scope' => 'Onderhoudswerkzaamheden zoals besproken, inclusief voorbereiding, herstelwerk en nette afwerking van de behandelde delen.',
                'werkzaamheden' => "- Inspectie en voorbereiding van de ondergrond\n- Herstelwerkzaamheden\n- Schilder- en/of afwerkingswerk\n- Controle en oplevering",
            ],
            'utiliteit' => [
                'samenvatting' => "Geachte {klant},\n\nBedankt voor uw aanvraag. Hierbij ontvangt u onze offerte voor de werkzaamheden aan uw bedrijfspand in {plaats}. We plannen het werk in overleg, zodat uw bedrijfsvoering zo min mogelijk hinder ondervindt.",
                'scope' => 'Renovatie-/afbouwwerkzaamheden aan het bedrijfspand volgens de besproken scope, uitgevoerd conform de geldende normen.',
                'werkzaamheden' => "- Voorbereiding en bouwplaatsinrichting\n- Bouwkundige aanpassingen\n- Installatiewerk in afstemming met uw technische partijen\n- Afwerking: wanden, plafonds en vloeren\n- Oplevering inclusief opleverrapport",
            ],
            default => [
                'samenvatting' => "Beste {klant},\n\nBedankt voor uw aanvraag. Hierbij ontvangt u onze offerte voor de renovatiewerkzaamheden aan uw woning in {plaats}. Renovion staat voor vakwerk: geen half werk en vage beloftes.",
                'scope' => 'Renovatiewerkzaamheden volgens de besproken wensen, inclusief coördinatie, materiaal en een nette oplevering.',
                'werkzaamheden' => "- Voorbereiding en bescherming van de werkplek\n- Renovatiewerkzaamheden volgens afspraak\n- Stuc-, tegel- en/of schilderwerk waar van toepassing\n- Eindcontrole, schoonmaak en oplevering",
            ],
        };
    }
}
