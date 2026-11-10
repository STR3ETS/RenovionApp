<?php

namespace App\Support;

use App\Enums\UserRole;

/**
 * De Academy: rondleidingen door het systeem, leerpaden per rol, en de
 * afspraken waar Renovion zich aan houdt.
 *
 * Een rondleiding is een reeks stappen die elk naar een scherm wijzen en
 * uitleggen wat je daar doet. Geen spotlight over de pagina, maar een korte
 * uitleg met de link erbij: dat werkt ook op een telefoon en blijft leesbaar
 * als het scherm later verandert.
 */
class Academy
{
    /** @var array<string, array{label: string, colour: string, icon: string}> */
    public const CATEGORIES = [
        'start' => ['label' => 'Aan de slag', 'colour' => 'brand', 'icon' => 'rocket-launch'],
        'verkoop' => ['label' => 'Verkoop en offertes', 'colour' => 'calc', 'icon' => 'funnel'],
        'project' => ['label' => 'Projecten en uitvoering', 'colour' => 'lead', 'icon' => 'folder'],
        'mensen' => ['label' => 'Mensen en partners', 'colour' => 'klant', 'icon' => 'identification'],
        'geld' => ['label' => 'Geld', 'colour' => 'green', 'icon' => 'banknotes'],
        'beheer' => ['label' => 'Beheer', 'colour' => 'planning', 'icon' => 'cog-6-tooth'],
    ];

    /**
     * De rondleidingen. Een stap met een route wijst naar dat scherm; een stap
     * zonder route legt iets uit dat over het hele systeem gaat.
     *
     * @var array<string, array{category: string, title: string, minutes: int, level: string, summary: string, steps: list<array{title: string, body: string, route?: string, module?: string}>}>
     */
    public const TOURS = [
        'basis' => [
            'category' => 'start',
            'title' => 'Rondleiding door Renovion',
            'minutes' => 5,
            'level' => 'Basis',
            'summary' => 'Waar vind je wat: je dag, de inbox, zoeken, nieuw aanmaken en Nova.',
            'steps' => [
                [
                    'title' => 'Vandaag is je startpunt',
                    'route' => 'dashboard',
                    'body' => 'Elke ochtend begin je hier. Je ziet wat er vandaag speelt en welke signalen aandacht vragen. De getallen zijn klikbaar.',
                ],
                [
                    'title' => 'De inbox is je werklijst',
                    'route' => 'attention.index',
                    'module' => 'inbox',
                    'body' => 'Alles wat aandacht vraagt staat hier: offertes die opvolging nodig hebben, werkbonnen ter controle, meldingen van de bouwplaats en voorstellen van agents. Werk hem van boven naar beneden af.',
                ],
                [
                    'title' => 'Het menu volgt het werk',
                    'body' => 'Werkdag, Werk, Bedrijf. Elke sectie heeft tabbladen bovenaan het scherm. Je ziet alleen wat bij jouw rol hoort, dus een collega kan iets anders zien dan jij.',
                ],
                [
                    'title' => 'Zoeken vanaf één plek',
                    'route' => 'search',
                    'module' => 'vandaag',
                    'body' => 'Typ een klantnaam, een projectnaam of een medewerker in de balk bovenaan. Je springt direct naar het dossier.',
                ],
                [
                    'title' => 'Nova stelt voor, jij beslist',
                    'body' => 'Nova staat rechtsonder. Stel vragen in gewone taal. Alles wat Nova voorstelt is een concept dat jij bevestigt; er gaat nooit iets naar buiten zonder dat een mens erop heeft gekeken.',
                ],
            ],
        ],
        'nova' => [
            'category' => 'start',
            'title' => 'Werken met Nova en de agents',
            'minutes' => 4,
            'level' => 'Basis',
            'summary' => 'Wat de acht agents doen, hoe je een voorstel goedkeurt en waar je het teruglees.',
            'steps' => [
                [
                    'title' => 'Acht agents, elk met één vak',
                    'route' => 'agents.index',
                    'module' => 'agents',
                    'body' => 'Lex doet sales en intake, Cato calculeert, Prep bereidt voor, Milo plant, Dex bewaakt het bewijs, Finn het geld, Liv de klantzorg. Nova verdeelt het werk. Elke agent heeft een menselijke eigenaar.',
                ],
                [
                    'title' => 'Jij keurt goed',
                    'route' => 'approvals.index',
                    'module' => 'goedkeuringen',
                    'body' => 'Alles wat naar een klant of partner gaat of geld raakt wacht hier op een mens. Een goedkeuring hoort bij precies één versie van het voorstel: verandert het daarna, dan vervalt de goedkeuring en moet je opnieuw kijken.',
                ],
                [
                    'title' => 'Alles is terug te lezen',
                    'route' => 'audit.index',
                    'module' => 'audit',
                    'body' => 'In de audit zie je bij elke gebeurtenis of een agent of een mens het deed. Dat is je controle achteraf.',
                ],
            ],
        ],
        'chat' => [
            'category' => 'start',
            'title' => 'Teamchat en taken uit gesprekken',
            'minutes' => 3,
            'level' => 'Basis',
            'summary' => 'Een kanaal per project, en van een bericht een taak maken.',
            'steps' => [
                [
                    'title' => 'Een kanaal per project',
                    'route' => 'chat.index',
                    'module' => 'teamchat',
                    'body' => 'Elk project heeft automatisch een eigen kanaal, naast de teamkanalen. Ongelezen berichten zie je aan de teller in het menu.',
                ],
                [
                    'title' => 'Van bericht naar taak',
                    'body' => 'Staat er een afspraak in een bericht, maak er dan een taak van. Het bericht blijft als bron aan die taak hangen, dus later is terug te zien waar het vandaan kwam.',
                ],
                [
                    'title' => 'Noem collega met @ erbij',
                    'body' => 'Typ @naam om iemand te noemen. Klanten komen nergens in de teamchat: die gesprekken lopen via het klantportaal.',
                ],
            ],
        ],
        'lead' => [
            'category' => 'verkoop',
            'title' => 'Van aanvraag tot intake',
            'minutes' => 4,
            'level' => 'Basis',
            'summary' => 'Een aanvraag oppakken, opvolgen en de intake in vijf stappen doorlopen.',
            'steps' => [
                [
                    'title' => 'Aanvragen komen hier binnen',
                    'route' => 'leads.index',
                    'module' => 'sales',
                    'body' => 'Via de website, telefoon of mail. Elke aanvraag krijgt een kwalificatie (koud, warm, heet) en een vervolgstap met datum.',
                ],
                [
                    'title' => 'Opvolging zet het dringendste bovenaan',
                    'route' => 'follow-ups.index',
                    'module' => 'opvolging',
                    'body' => 'Te laat bovenaan, dan wat niets gepland heeft. Een aanvraag zonder vervolgstap valt het snelst stil, dus die is gevaarlijker dan een die volgende week aan de beurt is.',
                ],
                [
                    'title' => 'De intake in vijf stappen',
                    'route' => 'intakes.index',
                    'module' => 'intake',
                    'body' => 'Aanvraag, belgesprek, afspraak voor de opname, de opname zelf, en de samenvatting. De score laat zien welke antwoorden een calculator nog mist. Wat je hier niet vraagt, mis je straks in de calculatie.',
                ],
            ],
        ],
        'offerte' => [
            'category' => 'verkoop',
            'title' => 'Begroting, offerte en opvolging',
            'minutes' => 6,
            'level' => 'Gevorderd',
            'summary' => 'Begroten met hoofdstukken, de staart, en wat het offertedashboard je vertelt.',
            'steps' => [
                [
                    'title' => 'Begroten met hoofdstukken',
                    'route' => 'budgets.index',
                    'module' => 'begrotingen',
                    'body' => 'Een begroting bestaat uit hoofdstukken met het nummer uit de standaard, paragrafen en regels. Een regel rekent hoeveelheid maal normuren tegen tarief, plus materiaal, materieel en onderaanneming.',
                ],
                [
                    'title' => 'De staart rekent cumulatief',
                    'body' => 'Algemene kosten gaan over de directe kosten, winst en risico daarna over het bedrag inclusief die algemene kosten. Daarom staat het bedrag per post erbij, en staan marge en opslag beide op het scherm: mensen denken in marge en rekenen in opslag.',
                ],
                [
                    'title' => 'Opties staan buiten de aanneemsom',
                    'body' => 'Tot de klant akkoord geeft. Vanaf dat moment telt het als meerwerk mee, met dezelfde staart als het gewone werk.',
                ],
                [
                    'title' => 'Het offertedashboard leest gedrag',
                    'route' => 'quote-dashboard',
                    'module' => 'offertedash',
                    'body' => 'De scoringskans komt niet uit je onderbuik maar uit wat de klant doet: vragen gesteld is zestig procent, twee keer gekeken vijfenveertig, een keer gekeken vijfendertig, nooit geopend vijftien.',
                ],
            ],
        ],
        'klus' => [
            'category' => 'project',
            'title' => 'Op de bouwplaats',
            'minutes' => 4,
            'level' => 'Basis',
            'summary' => 'Je klus uitvoeren, foto-bewijs maken, melden en afronden.',
            'steps' => [
                [
                    'title' => 'Mijn klussen is je dag',
                    'route' => 'jobs.index',
                    'module' => 'klussen',
                    'body' => 'Vandaag bovenaan, daarna wat er aankomt. Open je klus, meld je onderweg, en start na de veiligheidscheck.',
                ],
                [
                    'title' => 'Vijf keer ja, dan begin je',
                    'body' => 'De veiligheidscheck is de Laatste Minuut Risico Analyse. Alle vijf vragen moeten ja zijn. Dat is geen formaliteit: het is het moment waarop je stilstaat bij wat er mis kan gaan.',
                ],
                [
                    'title' => 'Foto bij elk bewijsmoment',
                    'body' => 'Bij sommige stappen hoort een foto. Die stap gaat niet af zonder, en de klus niet. Dat is precies wat je achteraf niet meer kunt maken: achter een dichtgezette wand, een waterdichting, een gemeten afschot.',
                ],
                [
                    'title' => 'Melden in plaats van zelf oplossen',
                    'body' => 'Loopt iets anders dan verwacht? Maak een melding. Die gaat naar de projectleider en naar het projectkanaal, en blijft open tot er antwoord is. Spoed betekent dat het werk stilligt.',
                ],
                [
                    'title' => 'Afronden levert de werkbon op',
                    'route' => 'work-orders.index',
                    'module' => 'werkbonnen',
                    'body' => 'Uren, materiaal en eventueel de naam van wie aftekende. De projectleider keurt hem goed; pas dan tellen de uren mee in kosten en voortgang.',
                ],
            ],
        ],
        'project' => [
            'category' => 'project',
            'title' => 'Projecten bewaken',
            'minutes' => 5,
            'level' => 'Gevorderd',
            'summary' => 'De stoplichten, het portfolio en de planning.',
            'steps' => [
                [
                    'title' => 'Vier stoplichten per project',
                    'route' => 'portfolio',
                    'module' => 'portfolio',
                    'body' => 'Planning, budget, kwaliteit en klant. Groen is op koers, oranje is aandacht, rood is actie nodig. De reden staat er altijd onder, dus je hoeft niet te zoeken waarom het rood staat.',
                ],
                [
                    'title' => 'Planning en capaciteit',
                    'route' => 'planning.index',
                    'module' => 'planning',
                    'body' => 'Wie staat waar, en hoe druk is iemand deze week. Afwezigheid haalt uren van de beschikbare capaciteit af, dus de bezetting klopt ook in een vakantieweek.',
                ],
                [
                    'title' => 'Nacalculatie tijdens het werk',
                    'route' => 'cost-reviews.index',
                    'module' => 'nacalculatie',
                    'body' => 'Begroot tegenover werkelijk en verwacht. Vroeg in een project weegt een urenmeting nog licht mee: één trage dag zegt weinig over het hele project.',
                ],
            ],
        ],
        'oplevering' => [
            'category' => 'project',
            'title' => 'Oplevering en nazorg',
            'minutes' => 4,
            'level' => 'Gevorderd',
            'summary' => 'De acht stappen van een oplevering, restpunten en service.',
            'steps' => [
                [
                    'title' => 'Acht stappen met een harde voorwaarde',
                    'route' => 'quality.index',
                    'module' => 'kwaliteit',
                    'body' => 'Je komt niet voorbij de eerste stap met werkbonnen die nog op goedkeuring wachten, niet naar de klantinspectie met open restpunten, en niet naar de documenten zonder getekend opleverformulier.',
                ],
                [
                    'title' => 'Restpunten met locatie',
                    'body' => 'Altijd met de plek erbij. "Kras in de wand" zonder locatie is onvindbaar voor wie het moet herstellen. Een restpunt kan aan een eigen mens of aan de partner die het werk deed.',
                ],
                [
                    'title' => 'Nazorg weet van garantie',
                    'route' => 'service.index',
                    'module' => 'nazorg',
                    'body' => 'Bij elke melding rekent het systeem uit of die nog binnen de garantietermijn valt, zodat niemand hoeft op te zoeken voor wiens rekening het werk komt.',
                ],
            ],
        ],
        'partner' => [
            'category' => 'mensen',
            'title' => 'Mensen, partners en prijsaanvragen',
            'minutes' => 4,
            'level' => 'Gevorderd',
            'summary' => 'Certificaten, het partnerdossier en werk uitzetten.',
            'steps' => [
                [
                    'title' => 'Certificaten verlopen',
                    'route' => 'workforce.index',
                    'module' => 'workforce',
                    'body' => 'VCA, BHV, NEN 3140 en de rest. Dertig dagen voor het verlopen gaat er een signaal af. Wie zonder geldig certificaat de bouwplaats op gaat, is een risico voor zichzelf en voor de opdrachtgever.',
                ],
                [
                    'title' => 'Negen punten in het partnerdossier',
                    'route' => 'subcontractors.index',
                    'module' => 'onderaannemers',
                    'body' => 'Legitimatie, vier documenten en vier overeenkomsten. Zolang daar iets aan mankeert krijgt een partij geen uitnodiging en geen opdracht; het systeem weigert dat met naam en reden.',
                ],
                [
                    'title' => 'Biedingen tegen het hoofdstuk',
                    'route' => 'rfqs.index',
                    'module' => 'werkhub',
                    'body' => 'Het budget komt uit het hoofdstuk van de begroting, dus je vergelijkt met een echt getal. Een bieding die niet alle posten prijst, geldt niet als vergelijkbaar.',
                ],
            ],
        ],
        'geld' => [
            'category' => 'geld',
            'title' => 'Facturatie en het financiële beeld',
            'minutes' => 5,
            'level' => 'Gevorderd',
            'summary' => 'Termijnen, de eindafrekening en de cashflowprognose.',
            'steps' => [
                [
                    'title' => 'Termijnen uit de begroting',
                    'route' => 'invoices.index',
                    'module' => 'facturatie',
                    'body' => 'Een termijnfactuur haalt zijn bedrag uit de termijn in de begroting, zodat de aanneemsom en de facturen nooit uit elkaar lopen.',
                ],
                [
                    'title' => 'De eindafrekening verrekent alles',
                    'body' => 'Goedgekeurd meerwerk erbij, akkoord minderwerk eraf, en wat al in termijnen is gefactureerd eraf. Bij een particuliere klant blijft vijf procent staan tot drie maanden na oplevering: dat is zijn opschortingsrecht.',
                ],
                [
                    'title' => 'Inkoop is ook een verplichting',
                    'route' => 'purchase-orders.index',
                    'module' => 'inkoop',
                    'body' => 'Een bestelling zonder factuur telt al mee in de prognose. Een inkoopfactuur telt pas als kosten na goedkeuring, zodat een foute factuur het projectresultaat niet vervuilt.',
                ],
                [
                    'title' => 'De prognose is een inschatting',
                    'route' => 'finance.index',
                    'module' => 'financieel',
                    'body' => 'Geen boekhouding: inkomsten op vervaldatum, uitgaven op leverdatum. Gebruik hem om te zien of er een maand aankomt waarin het krap wordt.',
                ],
            ],
        ],
        'beheer' => [
            'category' => 'beheer',
            'title' => 'Rollen, rechten en inrichting',
            'minutes' => 3,
            'level' => 'Gevorderd',
            'summary' => 'Wie ziet wat, en welke standaardwaarden het systeem gebruikt.',
            'steps' => [
                [
                    'title' => 'Elf rollen, elk met eigen schermen',
                    'route' => 'team.index',
                    'module' => 'teambeheer',
                    'body' => 'Wat iemand ziet hangt af van zijn rol. Mist een collega een menu-onderdeel dat jij wel hebt, dan is dat geen fout maar zijn rol.',
                ],
                [
                    'title' => 'Wat de agents mogen',
                    'route' => 'skills.index',
                    'module' => 'skills',
                    'body' => 'Per skill het niveau, een pauzeknop en een bedragsgrens waarboven altijd een mens beslist. Alleen directie richt dat in.',
                ],
                [
                    'title' => 'Standaardwaarden en geheimen',
                    'route' => 'settings.index',
                    'module' => 'instellingen',
                    'body' => 'Tarief, percentages en betaaltermijnen staan in de instellingen. Sleutels en tokens horen in .env: het scherm meldt alleen of ze er zijn, nooit de waarde.',
                ],
            ],
        ],
    ];

    /**
     * Leerpaden per rol: welke rondleidingen in welke volgorde.
     *
     * @var array<string, array{title: string, for: string, icon: string, colour: string, tours: list<string>, roles: list<string>|string}>
     */
    public const PATHS = [
        'start' => [
            'title' => 'Je eerste dag',
            'for' => 'Iedereen',
            'icon' => 'rocket-launch',
            'colour' => 'brand',
            'tours' => ['basis', 'nova', 'chat'],
            'roles' => '*',
        ],
        'verkoop' => [
            'title' => 'Verkoop en calculatie',
            'for' => 'Sales en calculator',
            'icon' => 'funnel',
            'colour' => 'calc',
            'tours' => ['lead', 'offerte'],
            'roles' => ['sales', 'calculator', 'directie', 'admin'],
        ],
        'project' => [
            'title' => 'Projectleiding',
            'for' => 'Projectleider en werkvoorbereider',
            'icon' => 'folder',
            'colour' => 'lead',
            'tours' => ['project', 'klus', 'oplevering', 'partner'],
            'roles' => ['projectleider', 'werkvoorbereider', 'directie', 'admin'],
        ],
        'bouwplaats' => [
            'title' => 'Op de bouwplaats',
            'for' => 'Uitvoerders',
            'icon' => 'identification',
            'colour' => 'green',
            'tours' => ['klus', 'oplevering'],
            'roles' => ['uitvoerder'],
        ],
        'geld' => [
            'title' => 'Administratie en inkoop',
            'for' => 'Administratie en inkoop',
            'icon' => 'banknotes',
            'colour' => 'klant',
            'tours' => ['geld', 'partner'],
            'roles' => ['administratie', 'inkoper'],
        ],
        'directie' => [
            'title' => 'Directie en beheer',
            'for' => 'Directie',
            'icon' => 'chart-bar',
            'colour' => 'planning',
            'tours' => ['project', 'geld', 'beheer'],
            'roles' => ['directie', 'admin'],
        ],
    ];

    /** Veelgestelde vragen, in de woorden waarin ze gesteld worden. */
    public const FAQ = [
        ['Waar begin ik als ik \'s ochtends inlog?', 'Op Vandaag. Werk daarna de inbox van boven naar beneden af: daar staat alles wat aandacht vraagt, en niets anders.'],
        ['Hoe maak ik een nieuwe aanvraag, taak of werkbon?', 'Via de oranje knop Nieuw rechtsboven. Die werkt op elk scherm.'],
        ['Een klant belt met een klacht na oplevering, wat doe ik?', 'Projecten, dan Nazorg en service, dan een melding aannemen. Het systeem rekent zelf uit of het nog onder garantie valt.'],
        ['Mag een agent zelf iets naar een klant sturen?', 'Nee. Alles wat naar buiten gaat of geld raakt wacht in Goedkeuringen op een mens.'],
        ['Hoe zie ik of een onderaannemer alles op orde heeft?', 'Mensen en partners, dan Onderaannemers. De kaart zegt of het dossier rond is, of welke punten open staan.'],
        ['Wat betekenen de stoplichten bij een project?', 'Planning, budget, kwaliteit en klant. Groen is op koers, oranje is aandacht, rood is actie nodig. De reden staat er altijd onder.'],
        ['Ik zie een menu-onderdeel niet dat een collega wel ziet.', 'Wat je ziet hangt af van je rol. Vraag de directie om je rol aan te passen bij Organisatie, Team en rechten.'],
        ['Werkt het systeem op mijn telefoon?', 'Ja, en Mijn klussen is er juist voor gemaakt. Zet de link op je beginscherm, dan werkt het als een app met de menubalk onderin.'],
        ['Waarom kan ik mijn klus niet afronden?', 'Dan mist er nog een verplichte foto bij een stap. Open die stap, maak de foto, en het gaat wel.'],
        ['Waarom vervalt een goedkeuring soms?', 'Omdat het voorstel veranderd is nadat erom gevraagd werd. Je zou dan iets anders goedkeuren dan er ligt, dus vragen we het opnieuw.'],
    ];

    /** De afspraken waar Renovion zich aan houdt. */
    public const RULES = [
        ['Elke klantvraag binnen één werkdag beantwoord', 'chat-bubble'],
        ['Elke afspraak, taak en wijziging staat in het systeem, niet in je hoofd', 'clipboard-check'],
        ['Geen gunning zonder compleet partnerdossier', 'users'],
        ['Werkbonnen dezelfde week ingevuld en goedgekeurd', 'wrench'],
        ['Meerwerk pas uitvoeren na akkoord van de klant', 'currency-euro'],
        ['Fotos bij elk kritisch moment, ook voor de Wkb', 'camera'],
    ];

    /**
     * De rondleidingen die bij een rol passen: een stap waarvan de module niet
     * voor die rol is, laten we weg. Wie geen geld mag zien, krijgt ook geen
     * uitleg over facturatie.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function toursFor(UserRole $role): array
    {
        $out = [];

        foreach (self::TOURS as $id => $tour) {
            $steps = array_values(array_filter(
                $tour['steps'],
                fn (array $step) => ! isset($step['module']) || Navigation::allows($role, $step['module']),
            ));

            // Zonder stappen heeft een rondleiding geen zin voor deze rol.
            if (count($steps) < 2) {
                continue;
            }

            $out[$id] = [...$tour, 'steps' => $steps];
        }

        return $out;
    }

    /** Het leerpad dat bij een rol hoort, of het startpad als er geen past. */
    public static function pathFor(UserRole $role): string
    {
        foreach (self::PATHS as $id => $path) {
            if ($path['roles'] !== '*' && in_array($role->value, (array) $path['roles'], true)) {
                return $id;
            }
        }

        return 'start';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function pathsFor(UserRole $role): array
    {
        return array_filter(
            self::PATHS,
            fn (array $path) => $path['roles'] === '*' || in_array($role->value, (array) $path['roles'], true),
        );
    }

    public static function chipClasses(string $colour): string
    {
        return AgentRegistry::chipClasses($colour);
    }
}
