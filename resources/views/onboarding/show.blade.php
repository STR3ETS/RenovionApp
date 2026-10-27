@php
    $canCrm = auth()->user()->can('manage-crm');
    $quotesEnabled = config('renovion.modules.quotes');
    $automationsEnabled = config('renovion.modules.automations');

    // Icon-chips in de categoriekleur van de module (stijlgids §03).
    $chip = fn (?string $category) => match ($category) {
        'lead' => 'bg-lead-100 text-lead-600',
        'klant' => 'bg-klant-100 text-klant-600',
        'calc' => 'bg-calc-100 text-calc-600',
        'offerte' => 'bg-offerte-100 text-offerte-600',
        'project' => 'bg-project-100 text-project-600',
        'planning' => 'bg-planning-100 text-planning-600',
        'brand' => 'bg-brand-100 text-brand-600',
        default => 'bg-navy-100 text-navy-700',
    };

    // De keten van aanvraag tot nazorg (briefing: één omgeving voor het hele proces).
    $flow = $canCrm ? array_values(array_filter([
        ['lead', 'inbox', 'Aanvraag komt binnen', 'Via de website of handmatig. Kwalificeer hem als koud, warm of heet, noteer de gewenste start en log elk contactmoment. Nova maakt direct een salestaak aan.'],
        ['calc', 'calculator', 'Calculatie', 'Bouw de calculatie uit de prijslijst. Typ of spreek de werkzaamheden in en Nova stelt regels voor die jij controleert. Onvoorzien, marge en btw worden berekend. Definitief maken zet de regels op slot.'],
        $quotesEnabled ? ['offerte', 'document-text', 'Offerte', 'Met één actie maak je van de calculatie een offerte. In de builder werk je hem af met blokken en sjablonen. De klant opent een persoonlijke link en tekent digitaal, vraagt een aanpassing of wijst af.'] : null,
        ['project', 'building-office', 'Akkoord wordt project', 'Bij akkoord ontstaat automatisch een project met negen fasen, van Opdracht en overdracht tot Nazorg. De aanvraag springt naar Project en alles staat in het klantdossier.'],
        ['planning', 'calendar', 'Werkvoorbereiding en planning', 'Per fase maak je werkpakketten met checklists en wijs je een verantwoordelijke aan. In de planning zet je uitvoerders op de juiste dagen.'],
        ['project', 'camera', 'Uitvoering met foto-bewijs', 'De uitvoerder vinkt op zijn telefoon checklistitems af en maakt foto\'s. Waar foto\'s verplicht zijn kan een item niet af zonder bewijs. Is alles gereed, dan geef je de fase vrij en start de volgende vanzelf.'],
        ['project', 'clipboard-check', 'Oplevering', 'Het opleverrapport wordt opgebouwd uit wat er is gebeurd: scope, planning, fasen, foto-bewijs, meerwerk en restpunten. Renovion tekent intern, de klant tekent in het portaal.'],
        ['brand', 'bell', 'Nazorg en Aandacht', 'Aandacht toont alleen uitzonderingen: een stille aanvraag, een offerte zonder reactie, een geblokkeerde fase, een verlopen deadline. Nova signaleert, jij beslist.'],
    ])) : [
        ['planning', 'home', 'Vandaag', 'Je startscherm op je telefoon: wat er vandaag voor je gepland staat en je open taken. Meer hoef je niet te zoeken.'],
        ['project', 'building-office', 'Project openen', 'Je ziet alleen de projecten waar je op staat. Op het project vind je de fase Uitvoering met de werkpakketten die voor jou klaarstaan.'],
        ['project', 'camera', 'Werkpakket afwerken', 'Vink de checklist af en maak foto\'s met de camera. Waar een camera-icoon staat is een foto verplicht: zonder bewijs kan het item niet af.'],
        ['project', 'clipboard-check', 'Taak afronden', 'Is de hele checklist af, dan tik je op "Taak afronden". De projectleider geeft daarna de fase vrij. Loop je vast? Zet het werkpakket op geblokkeerd, dan ziet kantoor het meteen.'],
        ['brand', 'chat-bubble', 'Team', 'Chat met kantoor en je collega\'s, per project of in het algemene kanaal. Stuur een foto mee of laat van een bericht een taak maken.'],
    ];

    // Waar je wat vindt, afgestemd op de rol.
    $modules = $canCrm ? array_values(array_filter([
        ['home', 'Vandaag', 'Je startscherm: de dagbriefing van Nova, wat aandacht vraagt, de taken van vandaag, de planning en de projecten die net zijn bijgewerkt.', route('dashboard'), null],
        ['exclamation-triangle', 'Aandacht', 'Het vangnet. Alleen wat afwijkt van het plan, gesorteerd op ernst. Elk item is een beslissing of actie waard en linkt direct naar de plek waar je het oplost.', route('attention.index'), 'brand'],
        ['inbox', 'Aanvragen & Sales', 'De pipeline van Nieuw tot Project. Per aanvraag: kwalificatie, gewenste start, contactmomenten en de volgende actie.', route('leads.index'), 'lead'],
        ['calculator', 'Calculaties', 'Kostprijsberekening per klus met regels uit de prijslijst, Nova-voorstellen en de totalen. Vanuit hier maak je de offerte.', route('calculations.index'), 'calc'],
        ['currency-euro', 'Prijslijst', 'De kostendatabase: arbeid, materiaal, materieel, onderaanneming en stelposten met automatische artikelnummers. Inline bewerken, aan en uit zetten.', route('price-items.index'), 'calc'],
        $quotesEnabled ? ['document-text', 'Offertes', 'Alle offertes met status en versiebeheer. De builder bouwt het document uit blokken; de klant krijgt een persoonlijke link om te tekenen.', route('quotes.index'), 'offerte'] : null,
        ['building-office', 'Projecten', 'Elk project met zijn fasen, werkpakketten, foto\'s, documenten en opleverrapport. De voortgang wordt berekend uit wat er gereed is.', route('projects.index'), 'project'],
        ['calendar', 'Planning', 'Wie werkt wanneer waar. Uitvoerders zien hier alleen hun eigen dagen.', route('planning.index'), 'planning'],
        ['clipboard-check', 'Taken', 'Te doen, bezig, wacht op, afgerond. Taken komen uit Nova, de chat, automations of maak je zelf.', route('tasks.index'), 'planning'],
        ['users', 'Klanten', 'Eén dossier per klant met een chronologische tijdlijn: aanvragen, offertes, projecten, notities en elke actie met wie, wat en wanneer. Hier maak je ook het portaal-account aan.', route('customers.index'), 'klant'],
        $automationsEnabled ? ['bolt', 'Automations', 'De regels waarmee Nova meekijkt. Per regel instelbaar: uit, signaleren, eerst bevestigen of automatisch uitvoeren.', route('automations.index'), 'brand'] : null,
        ['chat-bubble', 'Team', 'De teamchat: een kanaal per project plus algemene kanalen. Vraag @Nova om iets te doen, maak een taak van een bericht, deel foto\'s.', route('chat.index'), null],
        auth()->user()->can('manage-team') ? ['user', 'Teambeheer', 'Accounts en rollen van het team. Klant-accounts maak je niet hier maar vanaf de klantpagina.', route('team.index'), null] : null,
        ['magnifying-glass', 'Zoeken', 'De zoekbalk bovenin vindt klanten, projecten en aanvragen op naam, adres, e-mail of telefoon.', route('search'), null],
    ])) : [
        ['home', 'Vandaag', 'Je planning van deze week en je open taken.', route('dashboard'), 'planning'],
        ['building-office', 'Projecten', 'Jouw projecten met de werkpakketten en checklists waar je aan werkt.', route('projects.index'), 'project'],
        ['calendar', 'Planning', 'Jouw dagen: waar je wanneer wordt verwacht.', route('planning.index'), 'planning'],
        ['clipboard-check', 'Taken', 'Alles wat aan jou is toegewezen. Zet een taak op bezig of afgerond.', route('tasks.index'), 'planning'],
        ['chat-bubble', 'Team', 'De teamchat met kantoor en collega\'s.', route('chat.index'), null],
    ];

    $tips = $canCrm ? [
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
@endphp

<x-layouts.app title="Aan de slag">

    <x-page-header eyebrow="Welkom" title="Aan de slag" :subtitle="$canCrm ? 'Zo werkt Renovion Dash, en dit moet er staan voordat het team er elke dag mee werkt.' : 'Zo werk je met Renovion Dash op het werk.'">
        @if ($dismissed)
            <form method="POST" action="{{ route('onboarding.restore') }}">
                @csrf
                <button type="submit" class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-navy-900 transition hover:bg-gray-50">
                    <x-icon name="sparkles" class="h-4 w-4 text-gray-400" /> Weer in het menu tonen
                </button>
            </form>
        @else
            <form method="POST" action="{{ route('onboarding.dismiss') }}">
                @csrf
                <button type="submit" class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-navy-900 transition hover:bg-gray-50">
                    <x-icon name="check" class="h-4 w-4 text-gray-400" /> Uit het menu halen
                </button>
            </form>
        @endif
    </x-page-header>

    {{-- Hero: de belofte van het systeem plus de voortgang van de checklist --}}
    <section class="mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-navy-950 via-navy-950 to-navy-900 text-white">
        <div class="grid gap-6 p-6 lg:grid-cols-3 lg:p-8">
            <div class="lg:col-span-2">
                <p class="text-[11px] font-bold tracking-widest text-brand-400 uppercase">Eén omgeving voor het hele proces</p>
                @if ($canCrm)
                    <h2 class="mt-1 text-2xl font-bold lg:text-3xl">Van aanvraag tot nazorg, zonder losse lijstjes</h2>
                    <p class="mt-3 max-w-2xl text-sm leading-relaxed text-navy-100">
                        Aanvraag, calculatie, offerte, akkoord, projectfasen, uitvoering met foto-bewijs, oplevering en nazorg zitten in één systeem.
                        Het laat zelf zien wat vandaag aandacht vraagt. Nova automatiseert en stelt voor, jij houdt de regie.
                        Hieronder lees je hoe de keten werkt, wat er nog ingesteld moet worden en waar je alles vindt.
                    </p>
                @else
                    <h2 class="mt-1 text-2xl font-bold lg:text-3xl">Jouw werk, zonder gedoe</h2>
                    <p class="mt-3 max-w-2xl text-sm leading-relaxed text-navy-100">
                        Je ziet alleen wat voor jou is: je planning, je projecten en je taken. Op het werk vink je de checklist af en maak je foto's.
                        Kantoor ziet direct waar je staat, zonder dat je hoeft te bellen. Hieronder lees je hoe dat werkt.
                    </p>
                @endif
            </div>

            @if ($progress)
                <div class="rounded-2xl bg-white/5 p-5">
                    <div class="flex items-baseline justify-between gap-3">
                        <p class="text-[11px] font-bold tracking-widest text-navy-300 uppercase">Checklist</p>
                        <p class="text-2xl font-bold">{{ $progress['done'] }}<span class="text-sm font-medium text-navy-300"> van {{ $progress['total'] }}</span></p>
                    </div>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-white/10" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $progress['total'] }}" aria-valuenow="{{ $progress['done'] }}" aria-label="Voortgang van de checklist">
                        <div class="h-full rounded-full bg-brand-500 transition-[width]" style="width: {{ $progress['total'] > 0 ? round($progress['done'] / $progress['total'] * 100) : 0 }}%"></div>
                    </div>
                    <p class="mt-3 text-sm text-navy-200">
                        {{ $progress['complete'] ? 'Alles staat. Jullie kunnen beginnen.' : $progress['open'].' '.($progress['open'] === 1 ? 'stap' : 'stappen').' nog te doen.' }}
                    </p>
                    <a href="#checklist" class="mt-4 block rounded-xl bg-brand-500 py-2.5 text-center text-sm font-bold text-white transition hover:bg-brand-600">
                        Naar de checklist
                    </a>
                </div>
            @else
                <div class="rounded-2xl bg-white/5 p-5">
                    <p class="text-[11px] font-bold tracking-widest text-navy-300 uppercase">Je vaste ritme</p>
                    <ol class="mt-3 space-y-2 text-sm text-navy-100">
                        <li class="flex gap-2"><span class="font-bold text-brand-400">1</span> Kijk op Vandaag wat er staat.</li>
                        <li class="flex gap-2"><span class="font-bold text-brand-400">2</span> Open het werkpakket op het project.</li>
                        <li class="flex gap-2"><span class="font-bold text-brand-400">3</span> Vink af en maak foto's.</li>
                        <li class="flex gap-2"><span class="font-bold text-brand-400">4</span> Rond de taak af. Klaar.</li>
                    </ol>
                </div>
            @endif
        </div>
    </section>

    {{-- Zo werkt het --}}
    <section class="mb-8">
        <p class="text-[11px] font-bold tracking-widest text-gray-400 uppercase">Zo werkt het</p>
        <h2 class="mt-0.5 text-lg font-bold text-navy-950">{{ $canCrm ? 'Van aanvraag tot nazorg in '.count($flow).' stappen' : 'Jouw dag in '.count($flow).' stappen' }}</h2>
        <p class="mt-0.5 mb-4 text-sm text-gray-500">{{ $canCrm ? 'Elke stap bouwt voort op de vorige. Wat eenmaal is ingevoerd, hoef je nergens opnieuw te typen.' : 'Meer is het niet. Alles wat je invult ziet kantoor direct.' }}</p>

        <ol class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($flow as $index => [$category, $icon, $title, $text])
                <li class="flex flex-col gap-3 rounded-2xl border border-gray-200 bg-white p-5">
                    <div class="flex items-center justify-between">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl {{ $chip($category) }}">
                            <x-icon :name="$icon" class="h-5 w-5" />
                        </span>
                        <span class="text-[11px] font-bold tracking-widest text-gray-400 uppercase">Stap {{ $index + 1 }}</span>
                    </div>
                    <div>
                        <p class="font-semibold text-navy-900">{{ $title }}</p>
                        <p class="mt-1 text-sm leading-relaxed text-gray-500">{{ $text }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- Checklist: wat er moet staan om te beginnen --}}
    @if ($checklist)
        <section id="checklist" class="mb-8 scroll-mt-20">
            <p class="text-[11px] font-bold tracking-widest text-brand-600 uppercase">Checklist</p>
            <h2 class="mt-0.5 text-lg font-bold text-navy-950">Instellen om te beginnen</h2>
            <p class="mt-0.5 mb-4 text-sm text-gray-500">De status wordt live berekend uit de instellingen en de gegevens. Stappen met "optioneel" mogen later.</p>

            <ol class="space-y-2">
                @foreach ($items as $index => $item)
                    <li class="flex flex-wrap items-center gap-3 rounded-xl border bg-white p-4 {{ $item['done'] ? 'border-gray-100' : 'border-gray-200' }}" data-onboarding-item="{{ $item['key'] }}">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold {{ $item['done'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}" aria-hidden="true">
                            @if ($item['done'])
                                <x-icon name="check" class="h-4.5 w-4.5" />
                            @else
                                {{ $index + 1 }}
                            @endif
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-semibold text-navy-900">{{ $item['title'] }}</span>
                                @if ($item['done'])
                                    <span class="rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-semibold text-green-800">Klaar</span>
                                @elseif ($item['optional'])
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-600">Optioneel</span>
                                @else
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">Nog te doen</span>
                                @endif
                            </span>
                            <span class="mt-0.5 block text-xs leading-relaxed text-gray-500">{{ $item['description'] }}</span>
                            @if ($item['note'])
                                <span class="mt-0.5 block text-[11px] text-gray-400">{{ $item['note'] }}</span>
                            @endif
                        </span>
                        @if ($item['url'])
                            <a href="{{ $item['url'] }}" class="flex items-center gap-1.5 rounded-xl px-4 py-2 text-sm font-semibold transition {{ $item['done'] ? 'border border-gray-200 bg-white text-navy-900 hover:bg-gray-50' : 'bg-brand-500 text-white hover:bg-brand-600' }}">
                                {{ $item['action'] }}
                                <x-icon name="arrow-right" class="h-4 w-4" />
                            </a>
                        @endif
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    {{-- De onderdelen --}}
    <section class="mb-8">
        <p class="text-[11px] font-bold tracking-widest text-gray-400 uppercase">Onderdelen</p>
        <h2 class="mt-0.5 text-lg font-bold text-navy-950">Waar je wat vindt</h2>
        <p class="mt-0.5 mb-4 text-sm text-gray-500">{{ $canCrm ? 'In de volgorde van het menu links. Klik op een kaart om er direct heen te gaan.' : 'Dit zijn de knoppen in de balk onderin.' }}</p>

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($modules as [$icon, $title, $text, $url, $category])
                <a href="{{ $url }}" class="group flex gap-3 rounded-2xl border border-gray-200 bg-white p-4 transition hover:border-gray-300 hover:shadow-sm">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl {{ $chip($category) }}">
                        <x-icon :name="$icon" class="h-4.5 w-4.5" />
                    </span>
                    <span class="min-w-0">
                        <span class="flex items-center gap-1 text-sm font-semibold text-navy-900">
                            {{ $title }}
                            <x-icon name="chevron-right" class="h-3.5 w-3.5 text-gray-300 transition group-hover:translate-x-0.5 group-hover:text-brand-500" />
                        </span>
                        <span class="mt-0.5 block text-xs leading-relaxed text-gray-500">{{ $text }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- Nova --}}
    @if ($canCrm)
        <section class="mb-8 overflow-hidden rounded-2xl border border-gray-200 bg-white">
            <div class="grid gap-6 p-6 lg:grid-cols-[auto_1fr] lg:p-8">
                <x-nova-avatar class="h-16 w-16 ring-2 ring-brand-500" />
                <div class="min-w-0">
                    <p class="text-[11px] font-bold tracking-widest text-brand-600 uppercase">Nova</p>
                    <h2 class="mt-0.5 text-lg font-bold text-navy-950">Stelt voor, jij bevestigt</h2>
                    <p class="mt-1 max-w-2xl text-sm leading-relaxed text-gray-500">
                        Nova is de assistent in het oranje rondje rechtsonder. Alles wat Nova maakt is een concept dat jij eerst ziet en bevestigt.
                        Pas daarna gebeurt er iets, en dat wordt altijd vastgelegd.
                    </p>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <div class="flex gap-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $chip('brand') }}"><x-icon name="microphone" class="h-4 w-4" /></span>
                            <span><span class="block text-sm font-semibold text-navy-900">Spreek of typ een opdracht</span><span class="block text-xs text-gray-500">"Maak een taak voor Peter: morgen stucwerk Bakker nakijken." Nova maakt taken, afspraken en notities.</span></span>
                        </div>
                        <div class="flex gap-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $chip('brand') }}"><x-icon name="home" class="h-4 w-4" /></span>
                            <span><span class="block text-sm font-semibold text-navy-900">Dagbriefing</span><span class="block text-xs text-gray-500">Op Vandaag vat Nova samen wat er speelt en wat als eerste aandacht vraagt.</span></span>
                        </div>
                        <div class="flex gap-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $chip('calc') }}"><x-icon name="calculator" class="h-4 w-4" /></span>
                            <span><span class="block text-sm font-semibold text-navy-900">Calculatieregels en offerteposten</span><span class="block text-xs text-gray-500">Beschrijf de klus, Nova zoekt de passende artikelen uit de prijslijst. Jij controleert voordat ze erin komen.</span></span>
                        </div>
                        @if ($quotesEnabled)
                            <div class="flex gap-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $chip('offerte') }}"><x-icon name="pencil-square" class="h-4 w-4" /></span>
                                <span><span class="block text-sm font-semibold text-navy-900">Offerteteksten</span><span class="block text-xs text-gray-500">In de builder schrijft Nova een blok of de hele offerte. Vastgezette blokken blijven staan.</span></span>
                            </div>
                        @endif
                        <div class="flex gap-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $chip(null) }}"><x-icon name="chat-bubble" class="h-4 w-4" /></span>
                            <span><span class="block text-sm font-semibold text-navy-900">@Nova in de teamchat</span><span class="block text-xs text-gray-500">Noem @Nova in een bericht en je krijgt een voorstelkaart met "Ja, doe maar" of "Eerst aanpassen".</span></span>
                        </div>
                    </div>

                    @if ($automations->isNotEmpty())
                        <div class="mt-6 rounded-xl bg-gray-50 p-4">
                            <p class="text-xs font-semibold text-navy-900">Waar Nova op let (automations, elke 15 minuten)</p>
                            <ul class="mt-2 grid gap-x-6 gap-y-1.5 sm:grid-cols-2">
                                @foreach ($automations as $automation)
                                    <li class="flex gap-2 text-xs text-gray-500">
                                        <x-icon name="bolt" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-brand-500" />
                                        <span><span class="font-semibold text-navy-900">{{ $automation['name'] }}.</span> {{ $automation['description'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                            <a href="{{ route('automations.index') }}" class="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-brand-600 hover:text-brand-500">
                                Modus per regel instellen <x-icon name="arrow-right" class="h-3.5 w-3.5" />
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </section>
    @endif

    {{-- Goed om te weten --}}
    <section class="mb-4">
        <p class="text-[11px] font-bold tracking-widest text-gray-400 uppercase">Goed om te weten</p>
        <h2 class="mt-0.5 mb-4 text-lg font-bold text-navy-950">Voordat je begint</h2>

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($tips as [$icon, $title, $text])
                <div class="flex gap-3 rounded-2xl border border-gray-200 bg-white p-4">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-navy-100 text-navy-700">
                        <x-icon :name="$icon" class="h-4.5 w-4.5" />
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-navy-900">{{ $title }}</span>
                        <span class="mt-0.5 block text-xs leading-relaxed text-gray-500">{{ $text }}</span>
                    </span>
                </div>
            @endforeach
        </div>
    </section>

</x-layouts.app>
