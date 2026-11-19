<?php

namespace App\Support\App;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * De uitleg van "Aan de slag" voor de app (api/app/aan-de-slag): dezelfde teksten als
 * resources/views/onboarding/show.blade.php, per rol (kantoor of uitvoerder). Wijzig je daar een tekst,
 * pas hem hier ook aan. Paden zijn relatief, zodat de app ze met go() opent (scherm in de app of serverpagina).
 */
class OnboardingContent
{
    /** @return array{title: string, intro: string, subtitle: string} */
    public static function hero(bool $crm): array
    {
        return $crm ? [
            'subtitle' => 'Zo werkt Renovion OS, en dit moet er staan voordat het team er elke dag mee werkt.',
            'title' => 'Van aanvraag tot nazorg, zonder losse lijstjes',
            'intro' => 'Aanvraag, calculatie, offerte, akkoord, projectfasen, uitvoering met foto-bewijs, oplevering en nazorg zitten in één systeem. Het laat zelf zien wat vandaag aandacht vraagt. Nova automatiseert en stelt voor, jij houdt de regie. Hieronder lees je hoe de keten werkt, wat er nog ingesteld moet worden en waar je alles vindt.',
        ] : [
            'subtitle' => 'Zo werk je met Renovion OS op het werk.',
            'title' => 'Jouw werk, zonder gedoe',
            'intro' => 'Je ziet alleen wat voor jou is: je planning, je projecten en je taken. Op het werk vink je de checklist af en maak je foto\'s. Kantoor ziet direct waar je staat, zonder dat je hoeft te bellen. Hieronder lees je hoe dat werkt.',
        ];
    }

    /** Het vaste ritme van een uitvoerder (in plaats van de checklist). */
    public const RHYTHM = [
        'Kijk op Vandaag wat er staat.',
        'Open het werkpakket op het project.',
        'Vink af en maak foto\'s.',
        'Rond de taak af. Klaar.',
    ];

    /** @return list<array{icon: string, classes: string, title: string, text: string}> */
    public static function flow(bool $crm): array
    {
        $quotes = (bool) config('renovion.modules.quotes');

        $rows = $crm ? [
            ['lead', 'inbox', 'Aanvraag komt binnen', 'Via de website of handmatig. Kwalificeer hem als koud, warm of heet, noteer de gewenste start en log elk contactmoment. Nova maakt direct een salestaak aan.'],
            ['calc', 'calculator', 'Calculatie', 'Bouw de calculatie uit de prijslijst. Typ of spreek de werkzaamheden in en Nova stelt regels voor die jij controleert. Onvoorzien, marge en btw worden berekend. Definitief maken zet de regels op slot.'],
            $quotes ? ['offerte', 'document-text', 'Offerte', 'Met één actie maak je van de calculatie een offerte. In de builder werk je hem af met blokken en sjablonen. De klant opent een persoonlijke link en tekent digitaal, vraagt een aanpassing of wijst af.'] : null,
            ['project', 'building-office', 'Akkoord wordt project', 'Bij akkoord ontstaat automatisch een project met negen fasen, van Opdracht en overdracht tot Nazorg. De aanvraag springt naar Project en alles staat in het klantdossier.'],
            ['planning', 'calendar', 'Werkvoorbereiding en planning', 'Per fase maak je werkpakketten met checklists en wijs je een verantwoordelijke aan. In de planning zet je uitvoerders op de juiste dagen.'],
            ['project', 'camera', 'Uitvoering met foto-bewijs', 'De uitvoerder vinkt op zijn telefoon checklistitems af en maakt foto\'s. Waar foto\'s verplicht zijn kan een item niet af zonder bewijs. Is alles gereed, dan geef je de fase vrij en start de volgende vanzelf.'],
            ['project', 'clipboard-check', 'Oplevering', 'Het opleverrapport wordt opgebouwd uit wat er is gebeurd: scope, planning, fasen, foto-bewijs, meerwerk en restpunten. Renovion tekent intern, de klant tekent in het portaal.'],
            ['brand', 'bell', 'Nazorg en Aandacht', 'Aandacht toont alleen uitzonderingen: een stille aanvraag, een offerte zonder reactie, een geblokkeerde fase, een verlopen deadline. Nova signaleert, jij beslist.'],
        ] : [
            ['planning', 'home', 'Vandaag', 'Je startscherm op je telefoon: wat er vandaag voor je gepland staat en je open taken. Meer hoef je niet te zoeken.'],
            ['project', 'building-office', 'Project openen', 'Je ziet alleen de projecten waar je op staat. Op het project vind je de fase Uitvoering met de werkpakketten die voor jou klaarstaan.'],
            ['project', 'camera', 'Werkpakket afwerken', 'Vink de checklist af en maak foto\'s met de camera. Waar een camera-icoon staat is een foto verplicht: zonder bewijs kan het item niet af.'],
            ['project', 'clipboard-check', 'Taak afronden', 'Is de hele checklist af, dan tik je op "Taak afronden". De projectleider geeft daarna de fase vrij. Loop je vast? Zet het werkpakket op geblokkeerd, dan ziet kantoor het meteen.'],
            ['brand', 'chat-bubble', 'Team', 'Chat met kantoor en je collega\'s, per project of in het algemene kanaal. Stuur een foto mee of laat van een bericht een taak maken.'],
        ];

        return array_map(fn (array $row) => [
            'classes' => AppColours::chip($row[0]),
            'icon' => $row[1],
            'title' => $row[2],
            'text' => $row[3],
        ], array_values(array_filter($rows)));
    }

    /** @return list<array{icon: string, title: string, text: string, path: string|null, classes: string}> */
    public static function modules(User $user, bool $crm): array
    {
        $quotes = (bool) config('renovion.modules.quotes');
        $automations = (bool) config('renovion.modules.automations');

        $rows = $crm ? [
            ['home', 'Vandaag', 'Je startscherm: de dagbriefing van Nova, wat aandacht vraagt, de taken van vandaag, de planning en de projecten die net zijn bijgewerkt.', 'dashboard', null],
            ['exclamation-triangle', 'Aandacht', 'Het vangnet. Alleen wat afwijkt van het plan, gesorteerd op ernst. Elk item is een beslissing of actie waard en linkt direct naar de plek waar je het oplost.', 'attention.index', 'brand'],
            ['inbox', 'Aanvragen & Sales', 'De pipeline van Nieuw tot Project. Per aanvraag: kwalificatie, gewenste start, contactmomenten en de volgende actie.', 'leads.index', 'lead'],
            ['calculator', 'Calculaties', 'Kostprijsberekening per klus met regels uit de prijslijst, Nova-voorstellen en de totalen. Vanuit hier maak je de offerte.', 'calculations.index', 'calc'],
            ['currency-euro', 'Prijslijst', 'De kostendatabase: arbeid, materiaal, materieel, onderaanneming en stelposten met automatische artikelnummers. Inline bewerken, aan en uit zetten.', 'price-items.index', 'calc'],
            $quotes ? ['document-text', 'Offertes', 'Alle offertes met status en versiebeheer. De builder bouwt het document uit blokken; de klant krijgt een persoonlijke link om te tekenen.', 'quotes.index', 'offerte'] : null,
            ['building-office', 'Projecten', 'Elk project met zijn fasen, werkpakketten, foto\'s, documenten en opleverrapport. De voortgang wordt berekend uit wat er gereed is.', 'projects.index', 'project'],
            ['calendar', 'Planning', 'Wie werkt wanneer waar. Uitvoerders zien hier alleen hun eigen dagen.', 'planning.index', 'planning'],
            ['clipboard-check', 'Taken', 'Te doen, bezig, wacht op, afgerond. Taken komen uit Nova, de chat, automations of maak je zelf.', 'tasks.index', 'planning'],
            ['users', 'Klanten', 'Eén dossier per klant met een chronologische tijdlijn: aanvragen, offertes, projecten, notities en elke actie met wie, wat en wanneer. Hier maak je ook het portaal-account aan.', 'customers.index', 'klant'],
            $automations ? ['bolt', 'Automations', 'De regels waarmee Nova meekijkt. Per regel instelbaar: uit, signaleren, eerst bevestigen of automatisch uitvoeren.', 'automations.index', 'brand'] : null,
            ['chat-bubble', 'Team', 'De teamchat: een kanaal per project plus algemene kanalen. Vraag @Nova om iets te doen, maak een taak van een bericht, deel foto\'s.', 'chat.index', null],
            $user->can('manage-team') ? ['user', 'Teambeheer', 'Accounts en rollen van het team. Klant-accounts maak je niet hier maar vanaf de klantpagina.', 'team.index', null] : null,
            ['magnifying-glass', 'Zoeken', 'De zoekbalk bovenin vindt klanten, projecten en aanvragen op naam, adres, e-mail of telefoon.', 'search', null],
        ] : [
            ['home', 'Vandaag', 'Je planning van deze week en je open taken.', 'dashboard', 'planning'],
            ['building-office', 'Projecten', 'Jouw projecten met de werkpakketten en checklists waar je aan werkt.', 'projects.index', 'project'],
            ['calendar', 'Planning', 'Jouw dagen: waar je wanneer wordt verwacht.', 'planning.index', 'planning'],
            ['clipboard-check', 'Taken', 'Alles wat aan jou is toegewezen. Zet een taak op bezig of afgerond.', 'tasks.index', 'planning'],
            ['chat-bubble', 'Team', 'De teamchat met kantoor en collega\'s.', 'chat.index', null],
        ];

        return array_map(fn (array $row) => [
            'icon' => $row[0],
            'title' => $row[1],
            'text' => $row[2],
            'path' => Route::has($row[3]) ? route($row[3], [], false) : null,
            'classes' => AppColours::chip($row[4]),
        ], array_values(array_filter($rows)));
    }

    /** Wat Nova doet (alleen kantoor). @return list<array{icon: string, classes: string, title: string, text: string}> */
    public static function nova(): array
    {
        $rows = [
            ['brand', 'microphone', 'Spreek of typ een opdracht', '"Maak een taak voor Peter: morgen stucwerk Bakker nakijken." Nova maakt taken, afspraken en notities.'],
            ['brand', 'home', 'Dagbriefing', 'Op Vandaag vat Nova samen wat er speelt en wat als eerste aandacht vraagt.'],
            ['calc', 'calculator', 'Calculatieregels en offerteposten', 'Beschrijf de klus, Nova zoekt de passende artikelen uit de prijslijst. Jij controleert voordat ze erin komen.'],
            config('renovion.modules.quotes') ? ['offerte', 'pencil-square', 'Offerteteksten', 'In de builder schrijft Nova een blok of de hele offerte. Vastgezette blokken blijven staan.'] : null,
            [null, 'chat-bubble', '@Nova in de teamchat', 'Noem @Nova in een bericht en je krijgt een voorstelkaart met "Ja, doe maar" of "Eerst aanpassen".'],
        ];

        return array_map(fn (array $row) => [
            'classes' => AppColours::chip($row[0]),
            'icon' => $row[1],
            'title' => $row[2],
            'text' => $row[3],
        ], array_values(array_filter($rows)));
    }

    /** @return list<array{icon: string, title: string, text: string}> */
    public static function tips(bool $crm): array
    {
        $rows = $crm ? [
            ['shield-check', 'Rollen en rechten', 'Admin, Sales, Projectleider en Werkvoorbereider zien de hele interne omgeving. Uitvoerders zien alleen hun eigen projecten, taken en planning. Klanten komen uitsluitend in het portaal. Alleen een admin beheert accounts en automations.'],
            ['device-phone-mobile', 'Alles werkt op de telefoon', 'Het systeem is gebouwd voor mobiel gebruik. Uitvoerders werken volledig vanaf hun telefoon, met camera-upload en grote knoppen. Op de desktop heb je dezelfde functies met meer overzicht.'],
            ['lock-closed', 'Persoonlijke links voor klanten', 'Een klant hoeft niet in te loggen om een offerte te bekijken of te tekenen: de offerte heeft een eigen privélink. Voor voortgang, foto\'s en akkoorden maak je vanaf de klantpagina een portaal-account aan.'],
            ['clock', 'Alles wordt vastgelegd', 'Bij elke belangrijke actie wordt bijgehouden wie wat wanneer deed, met de oude en nieuwe waarde en de bron: handmatig, Nova, automation, website of klantportaal. Je ziet het terug op de tijdlijn van de klant.'],
            ['arrow-path', 'Je kunt niets kapotmaken', 'Nova en de automations voeren niets uit zonder bevestiging, tenzij je een regel bewust op automatisch zet. Verstuurde offerteversies worden bevroren, een nieuwe versie vraagt een wijzigingslog.'],
            ['question-mark-circle', 'Hulp nodig?', 'Loopt iets niet zoals verwacht of wil je iets anders ingericht hebben? Neem contact op met Eazyonline. Koppelingen, mail en serverinstellingen regelen wij voor jullie.'],
        ] : [
            ['device-phone-mobile', 'Werk vanaf je telefoon', 'Alles wat je nodig hebt staat in de balk onderin: Vandaag, Projecten, Planning, Chat en Acties. Foto\'s maak je direct met de camera.'],
            ['camera', 'Foto\'s zijn bewijs', 'Een foto hoort altijd bij een checklistitem of werkpakket, nooit los. Zo kan kantoor en de klant zien wat er is gedaan. Maak ze op het moment zelf, niet achteraf.'],
            ['exclamation-triangle', 'Loop je vast?', 'Zet het werkpakket op "Geblokkeerd" of "Wacht op leverancier" en zeg in de projectchat wat er aan de hand is. Kantoor ziet het direct bij Aandacht.'],
            ['question-mark-circle', 'Hulp nodig?', 'Vraag het in de teamchat of aan je projectleider. Werkt iets niet op je telefoon? Geef het door aan kantoor, dan komt het bij Eazyonline terecht.'],
        ];

        return array_map(fn (array $row) => ['icon' => $row[0], 'title' => $row[1], 'text' => $row[2]], $rows);
    }
}
