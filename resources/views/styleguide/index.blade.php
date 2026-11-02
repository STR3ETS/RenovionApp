<x-layouts.app title="Stijlgids">

    @php
        // Volledige klassenamen (geen interpolatie): Tailwind leest ze uit dit bestand.
        $categories = [
            ['naam' => 'Aanvragen', 'icon' => 'inbox', 'hex' => '#0EA5E9', 'basis' => 'sky', 'token' => 'lead',
             'chip' => 'bg-lead-500 text-white', 'soft' => 'border-lead-500/30 bg-lead-500/10', 'pill' => 'bg-lead-500/15 text-lead-300', 'tekst' => 'text-lead-400', 'accent' => 'border-l-lead-500'],
            ['naam' => 'Klanten', 'icon' => 'users', 'hex' => '#14B8A6', 'basis' => 'teal', 'token' => 'klant',
             'chip' => 'bg-klant-500 text-white', 'soft' => 'border-klant-500/30 bg-klant-500/10', 'pill' => 'bg-klant-500/15 text-klant-300', 'tekst' => 'text-klant-400', 'accent' => 'border-l-klant-500'],
            ['naam' => 'Calculaties', 'icon' => 'calculator', 'hex' => '#8B5CF6', 'basis' => 'violet', 'token' => 'calc',
             'chip' => 'bg-calc-500 text-white', 'soft' => 'border-calc-500/30 bg-calc-500/10', 'pill' => 'bg-calc-500/15 text-calc-300', 'tekst' => 'text-calc-400', 'accent' => 'border-l-calc-500'],
            ['naam' => 'Offertes', 'icon' => 'document-text', 'hex' => '#6366F1', 'basis' => 'indigo', 'token' => 'offerte',
             'chip' => 'bg-offerte-500 text-white', 'soft' => 'border-offerte-500/30 bg-offerte-500/10', 'pill' => 'bg-offerte-500/15 text-offerte-300', 'tekst' => 'text-offerte-400', 'accent' => 'border-l-offerte-500'],
            ['naam' => 'Projecten', 'icon' => 'building-office', 'hex' => '#22C55E', 'basis' => 'groen', 'token' => 'project',
             'chip' => 'bg-project-500 text-white', 'soft' => 'border-project-500/30 bg-project-500/10', 'pill' => 'bg-project-500/15 text-project-300', 'tekst' => 'text-project-400', 'accent' => 'border-l-project-500'],
            ['naam' => 'Planning', 'icon' => 'calendar', 'hex' => '#EC4899', 'basis' => 'roze', 'token' => 'planning',
             'chip' => 'bg-planning-500 text-white', 'soft' => 'border-planning-500/30 bg-planning-500/10', 'pill' => 'bg-planning-500/15 text-planning-300', 'tekst' => 'text-planning-400', 'accent' => 'border-l-planning-500'],
        ];
    @endphp

    {{-- Kop --}}
    <section class="mb-8 overflow-hidden rounded-2xl bg-navy-950 p-6 text-white lg:p-8">
        <p class="text-[11px] font-bold tracking-widest text-brand-400 uppercase">Renovion Dash, ontwerp</p>
        <h1 class="mt-1 text-2xl font-bold lg:text-3xl">Stijlgids</h1>
        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-navy-200">
            De frisse huisstijl van Renovion Dash: één actiekleur, een eigen kleur per domein zodat je in één oogopslag ziet
            waar iets over gaat, en kaarten met karakter in plaats van grijs op grijs.
        </p>
        <a href="{{ route('dashboard') }}" class="mt-4 inline-block rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600">
            Bekijk het dashboard in deze stijl →
        </a>
    </section>

    <div class="max-w-5xl space-y-10">

        {{-- 1. Principes --}}
        <section>
            <p class="text-[11px] font-bold tracking-widest text-slate-500 uppercase">01. Principes</p>
            <h2 class="mt-1 mb-4 text-xl font-bold text-white">Waar alles op rust</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-500 text-white"><x-icon name="bolt" class="h-4 w-4" /></span>
                    <p class="mt-3 text-sm font-bold text-white">Eén actiekleur</p>
                    <p class="mt-1 text-xs leading-relaxed text-slate-400">Oranje is uitsluitend voor de primaire actie. Staat er oranje, dan kun je klikken.</p>
                </div>
                <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-offerte-500 text-white"><x-icon name="eye" class="h-4 w-4" /></span>
                    <p class="mt-3 text-sm font-bold text-white">Kleur = categorie</p>
                    <p class="mt-1 text-xs leading-relaxed text-slate-400">Elk domein heeft een vaste tint. Een chip of accentlijn vertelt zonder lezen waar een kaart over gaat.</p>
                </div>
                <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-navy-950 text-white"><x-icon name="check" class="h-4 w-4" /></span>
                    <p class="mt-3 text-sm font-bold text-white">Eén donker anker</p>
                    <p class="mt-1 text-xs leading-relaxed text-slate-400">Maximaal één navy kaart per scherm (Nova, hero). De rest blijft licht en luchtig.</p>
                </div>
                <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-klant-500 text-white"><x-icon name="users" class="h-4 w-4" /></span>
                    <p class="mt-3 text-sm font-bold text-white">Mobile-first, geen emoji's</p>
                    <p class="mt-1 text-xs leading-relaxed text-slate-400">Uitvoerders werken op de telefoon. Iconen zijn heroicons via <code class="rounded bg-surface-750 px-1">x-icon</code>, nooit emoji's.</p>
                </div>
            </div>
        </section>

        {{-- 2. Merkkleuren --}}
        <section>
            <p class="text-[11px] font-bold tracking-widest text-slate-500 uppercase">02. Merkkleuren</p>
            <h2 class="mt-1 mb-4 text-xl font-bold text-white">Oranje doet, navy draagt</h2>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="overflow-hidden rounded-2xl border border-surface-700 bg-surface-800">
                    <div class="flex h-24 items-end bg-brand-500 p-4"><p class="text-sm font-bold text-white">brand-500. #F85B0B</p></div>
                    <div class="p-4 text-xs leading-relaxed text-slate-400">
                        <p class="font-bold text-white">Actie</p>
                        Primaire knoppen, actieve navigatie, de "+ Nieuw". Nooit als sfeer- of categoriekleur: schaars houden, dan blijft hij sturend.
                    </div>
                </div>
                <div class="overflow-hidden rounded-2xl border border-surface-700 bg-surface-800">
                    <div class="flex h-24 items-end bg-navy-950 p-4"><p class="text-sm font-bold text-white">navy-950, #070724</p></div>
                    <div class="p-4 text-xs leading-relaxed text-slate-400">
                        <p class="font-bold text-white">Fundament</p>
                        Sidebar, koppen, de ene donkere ankerkaart per scherm (Nova, offerte-hero) en alle belangrijke tekst (navy-900/950).
                    </div>
                </div>
            </div>
        </section>

        {{-- 3. Categoriekleuren --}}
        <section>
            <p class="text-[11px] font-bold tracking-widest text-slate-500 uppercase">03. Categoriekleuren</p>
            <h2 class="mt-1 text-xl font-bold text-white">Zien waar het over gaat, zonder te lezen</h2>
            <p class="mt-1 mb-4 max-w-2xl text-sm text-slate-400">
                Elke module een vaste tint, altijd in drie vormen: een gevulde icon-chip, een zachte pill en (spaarzaam) een getinte kaart
                of accentlijn links. Nooit voor knoppen - dat blijft oranje.
            </p>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($categories as $cat)
                    <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl {{ $cat['chip'] }}"><x-icon :name="$cat['icon']" class="h-5 w-5" /></span>
                                <div>
                                    <p class="text-sm font-bold text-white">{{ $cat['naam'] }}</p>
                                    <p class="text-[11px] font-medium text-slate-500">{{ $cat['hex'] }}, {{ $cat['basis'] }}, <code>{{ $cat['token'] }}-*</code></p>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $cat['pill'] }}">{{ $cat['naam'] }}</span>
                            <span class="text-xs font-semibold {{ $cat['tekst'] }}">Linktekst →</span>
                        </div>
                        <div class="mt-3 rounded-xl border bg-surface-800 py-2 pr-3 pl-3 text-xs font-medium text-slate-300 border-l-4 {{ $cat['accent'] }}">Lijstrij met accentlijn</div>
                        <div class="mt-2 rounded-xl border p-3 text-xs font-medium text-white {{ $cat['soft'] }}">Zachte kaart (bijv. statcard)</div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- 4. Statuskleuren --}}
        <section>
            <p class="text-[11px] font-bold tracking-widest text-slate-500 uppercase">04. Statuskleuren</p>
            <h2 class="mt-1 mb-1 text-xl font-bold text-white">Betekenis, geen decoratie</h2>
            <p class="mb-4 max-w-2xl text-sm text-slate-400">Groen, oranje, rood en grijs zijn gereserveerd voor status (briefing §7). Gebruik ze nergens anders voor, dan blijven ze betrouwbaar.</p>
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="flex items-center gap-3 rounded-2xl border border-surface-700 bg-surface-800 p-4">
                    <x-signal-dot color="green" class="h-3 w-3" />
                    <div><p class="text-sm font-bold text-white">Op schema</p><p class="text-[11px] text-slate-500">gereed, akkoord</p></div>
                </div>
                <div class="flex items-center gap-3 rounded-2xl border border-surface-700 bg-surface-800 p-4">
                    <x-signal-dot color="amber" class="h-3 w-3" />
                    <div><p class="text-sm font-bold text-white">Aandacht</p><p class="text-[11px] text-slate-500">wachten, opvolgen</p></div>
                </div>
                <div class="flex items-center gap-3 rounded-2xl border border-surface-700 bg-surface-800 p-4">
                    <x-signal-dot color="red" class="h-3 w-3" />
                    <div><p class="text-sm font-bold text-white">Geblokkeerd</p><p class="text-[11px] text-slate-500">te laat, risico</p></div>
                </div>
                <div class="flex items-center gap-3 rounded-2xl border border-surface-700 bg-surface-800 p-4">
                    <x-signal-dot color="gray" class="h-3 w-3" />
                    <div><p class="text-sm font-bold text-white">Niet gestart</p><p class="text-[11px] text-slate-500">concept, inactief</p></div>
                </div>
            </div>
        </section>

        {{-- 5. Typografie --}}
        <section>
            <p class="text-[11px] font-bold tracking-widest text-slate-500 uppercase">05. Typografie</p>
            <h2 class="mt-1 mb-4 text-xl font-bold text-white">Inter Tight, grote cijfers, kleine labels</h2>
            <div class="space-y-5 rounded-2xl border border-surface-700 bg-surface-800 p-6 lg:p-8">
                <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-surface-700 pb-4">
                    <p class="text-[11px] font-bold tracking-widest text-offerte-400 uppercase">Eyebrow, sectielabel</p>
                    <code class="text-[11px] text-slate-500">text-[11px] font-bold uppercase tracking-widest</code>
                </div>
                <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-surface-700 pb-4">
                    <p class="text-3xl font-bold text-white">€ 128.400</p>
                    <code class="text-[11px] text-slate-500">text-3xl font-bold - kerngetallen</code>
                </div>
                <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-surface-700 pb-4">
                    <h3 class="text-2xl font-bold text-white">Paginakop (H1)</h3>
                    <code class="text-[11px] text-slate-500">text-2xl lg:text-3xl font-bold</code>
                </div>
                <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-surface-700 pb-4">
                    <h4 class="text-base font-bold text-white">Sectiekop (H2)</h4>
                    <code class="text-[11px] text-slate-500">text-base font-bold</code>
                </div>
                <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-surface-700 pb-4">
                    <p class="text-sm text-slate-300">Lopende tekst: kort, concreet, Nederlands. Actie vóór informatie.</p>
                    <code class="text-[11px] text-slate-500">text-sm text-slate-300</code>
                </div>
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <p class="text-xs text-slate-500">Bijschrift of metadata, 14 feb, door Imad</p>
                    <code class="text-[11px] text-slate-500">text-xs text-slate-500</code>
                </div>
            </div>
        </section>

        {{-- 6. Bouwstenen --}}
        <section>
            <p class="text-[11px] font-bold tracking-widest text-slate-500 uppercase">06. Bouwstenen</p>
            <h2 class="mt-1 mb-4 text-xl font-bold text-white">Knoppen, kaarten en rijen</h2>

            <div class="space-y-4">
                {{-- Knoppen --}}
                <div class="rounded-2xl border border-surface-700 bg-surface-800 p-6">
                    <p class="mb-3 text-sm font-bold text-white">Knoppen - één primaire per schermdeel</p>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" class="rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600">Primair (oranje)</button>
                        <button type="button" class="rounded-xl bg-navy-950 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-navy-900">Secundair (navy)</button>
                        <button type="button" class="rounded-xl border border-surface-600 bg-surface-800 px-5 py-2.5 text-sm font-semibold text-slate-300 transition hover:bg-surface-850">Tertiair</button>
                        <button type="button" class="text-sm font-semibold text-brand-400 transition hover:text-brand-500">Tekstlink →</button>
                        <button type="button" class="rounded-xl border border-red-500/30 bg-surface-800 px-5 py-2.5 text-sm font-semibold text-red-400 transition hover:bg-red-500/10">Verwijderen</button>
                    </div>
                </div>

                {{-- Statcard nieuw --}}
                <div class="rounded-2xl border border-surface-700 bg-surface-800 p-6">
                    <p class="mb-1 text-sm font-bold text-white">Statcard - zachte categorietint + gevulde chip</p>
                    <p class="mb-3 text-xs text-slate-500">Zo herken je op afstand welk cijfer bij welk domein hoort. Alert-regel in signaalkleur.</p>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-2xl border border-project-500/30 bg-project-500/10 p-4">
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-xs font-semibold text-project-300">Actieve projecten</p>
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-project-500 text-white"><x-icon name="building-office" class="h-4 w-4" /></span>
                            </div>
                            <p class="mt-1 text-3xl font-bold text-white">7</p>
                            <p class="mt-0.5 text-xs font-medium text-red-400">2 vragen aandacht</p>
                        </div>
                        <div class="rounded-2xl border border-lead-500/30 bg-lead-500/10 p-4">
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-xs font-semibold text-lead-300">Nieuwe aanvragen</p>
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-lead-500 text-white"><x-icon name="inbox" class="h-4 w-4" /></span>
                            </div>
                            <p class="mt-1 text-3xl font-bold text-white">3</p>
                            <p class="mt-0.5 text-xs font-medium text-slate-500">8 open in de pipeline</p>
                        </div>
                        <div class="rounded-2xl border border-offerte-500/30 bg-offerte-500/10 p-4">
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-xs font-semibold text-offerte-300">Open offertes</p>
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-offerte-500 text-white"><x-icon name="document-text" class="h-4 w-4" /></span>
                            </div>
                            <p class="mt-1 text-3xl font-bold text-white">4</p>
                            <p class="mt-0.5 text-xs font-medium text-slate-500">€ 46.200 in behandeling</p>
                        </div>
                    </div>
                </div>

                {{-- Lijstrij --}}
                <div class="rounded-2xl border border-surface-700 bg-surface-800 p-6">
                    <p class="mb-1 text-sm font-bold text-white">Actie-rij - chip vertelt de categorie, dot de urgentie</p>
                    <p class="mb-3 text-xs text-slate-500">Voor "Nu doen", Aandacht en andere lijsten. Wit houden; de kleur zit in chip en accentlijn.</p>
                    <div class="space-y-2">
                        <div class="flex items-center gap-3 rounded-xl border border-surface-700 border-l-4 border-l-offerte-500 bg-surface-800 p-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-offerte-500/15 text-offerte-400"><x-icon name="document-text" class="h-4 w-4" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-white">Familie Jansen: offerte 5 dagen open</span>
                                <span class="block truncate text-xs text-slate-400">€ 18.400. 2× bekeken</span>
                            </span>
                            <x-signal-dot color="amber" />
                        </div>
                        <div class="flex items-center gap-3 rounded-xl border border-surface-700 border-l-4 border-l-lead-500 bg-surface-800 p-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-lead-500/15 text-lead-400"><x-icon name="phone" class="h-4 w-4" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-white">De Vries: nieuwe aanvraag opvolgen</span>
                                <span class="block truncate text-xs text-slate-400">Badkamerrenovatie, vandaag 15:00</span>
                            </span>
                            <x-signal-dot color="red" />
                        </div>
                    </div>
                </div>

                {{-- Kaartvarianten --}}
                <div class="grid gap-3 lg:grid-cols-3">
                    <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                        <p class="text-sm font-bold text-white">Standaardkaart</p>
                        <p class="mt-1 text-xs leading-relaxed text-slate-400">Wit, <code>rounded-2xl border-surface-700</code>. Het werkpaard - maar nooit vijf identieke naast elkaar zonder chip of accent.</p>
                    </div>
                    <div class="rounded-2xl border border-calc-500/30 bg-calc-500/10 p-5">
                        <span class="rounded-full bg-calc-500/15 px-2.5 py-0.5 text-[11px] font-semibold text-calc-300">Calculaties</span>
                        <p class="mt-2 text-sm font-bold text-white">Getinte kaart</p>
                        <p class="mt-1 text-xs leading-relaxed text-slate-300">Zachte categorietint voor samenvattingen en statcards. Maximaal één tint per kaart.</p>
                    </div>
                    <div class="rounded-2xl bg-navy-950 p-5 text-white">
                        <p class="text-[11px] font-bold tracking-widest text-brand-400 uppercase">Anker</p>
                        <p class="mt-1 text-sm font-bold">Donkere kaart</p>
                        <p class="mt-1 text-xs leading-relaxed text-navy-200">Voor Nova en hero's. Eén per scherm, altijd met een oranje accent erin.</p>
                    </div>
                </div>

                {{-- Formulier + empty state --}}
                <div class="grid gap-3 lg:grid-cols-2">
                    <div class="rounded-2xl border border-surface-700 bg-surface-800 p-6">
                        <p class="mb-3 text-sm font-bold text-white">Formulierveld</p>
                        <label class="mb-1 block text-xs font-semibold text-slate-400" for="sg-veld">Klantnaam</label>
                        <input type="text" id="sg-veld" placeholder="Familie Jansen" class="w-full rounded-xl border-surface-600 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <p class="mt-2 text-[11px] text-slate-500">Focus is altijd oranje; labels klein en grijs erboven.</p>
                    </div>
                    <div class="rounded-2xl border border-surface-700 bg-surface-800 p-6">
                        <p class="mb-3 text-sm font-bold text-white">Lege staat</p>
                        <div class="rounded-xl border border-dashed border-surface-600 p-6 text-center">
                            <span class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-planning-500/15 text-planning-400"><x-icon name="calendar" class="h-5 w-5" /></span>
                            <p class="text-sm font-semibold text-white">Niets gepland voor vandaag</p>
                            <p class="mt-0.5 text-xs text-slate-500">Met categorie-icoon, nooit alleen tekst.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- 7. Spelregels --}}
        <section>
            <p class="text-[11px] font-bold tracking-widest text-slate-500 uppercase">07. Spelregels</p>
            <h2 class="mt-1 mb-4 text-xl font-bold text-white">Zo blijft het fris</h2>
            <div class="grid gap-3 lg:grid-cols-2">
                <div class="rounded-2xl border border-project-500/30 bg-project-500/10 p-6">
                    <p class="mb-3 flex items-center gap-2 text-sm font-bold text-project-300"><x-icon name="check" class="h-4 w-4" /> Wel doen</p>
                    <ul class="space-y-2 text-sm text-slate-200">
                        <li class="flex gap-2"><span class="mt-2 h-1 w-1 shrink-0 rounded-full bg-project-500"></span>Elke kaart één duidelijk contrastpunt: een chip, pill of accentlijn in de categoriekleur.</li>
                        <li class="flex gap-2"><span class="mt-2 h-1 w-1 shrink-0 rounded-full bg-project-500"></span>Eyebrow-label boven secties, groot cijfer, klein onderschrift.</li>
                        <li class="flex gap-2"><span class="mt-2 h-1 w-1 shrink-0 rounded-full bg-project-500"></span>Witruimte via <code class="rounded bg-surface-800 px-1">gap</code>, kaarten ademen (p-5/p-6).</li>
                        <li class="flex gap-2"><span class="mt-2 h-1 w-1 shrink-0 rounded-full bg-project-500"></span>Eén navy ankerkaart per scherm, met oranje accent erin.</li>
                        <li class="flex gap-2"><span class="mt-2 h-1 w-1 shrink-0 rounded-full bg-project-500"></span>Statuskleuren alleen voor status; categoriekleuren alleen voor herkenning.</li>
                    </ul>
                </div>
                <div class="rounded-2xl border border-red-500/30 bg-red-500/10 p-6">
                    <p class="mb-3 flex items-center gap-2 text-sm font-bold text-red-300"><x-icon name="x-mark" class="h-4 w-4" /> Niet doen</p>
                    <ul class="space-y-2 text-sm text-slate-200">
                        <li class="flex gap-2"><span class="mt-2 h-1 w-1 shrink-0 rounded-full bg-red-500"></span>Rijen identieke kaarten zonder enig accent, alles even donkergrijs.</li>
                        <li class="flex gap-2"><span class="mt-2 h-1 w-1 shrink-0 rounded-full bg-red-500"></span>Categoriekleuren op knoppen - actie is altijd oranje.</li>
                        <li class="flex gap-2"><span class="mt-2 h-1 w-1 shrink-0 rounded-full bg-red-500"></span>Meer dan één tint of meer dan één donkere kaart per schermdeel.</li>
                        <li class="flex gap-2"><span class="mt-2 h-1 w-1 shrink-0 rounded-full bg-red-500"></span>Emoji's, staalblauw (<code class="rounded bg-surface-800 px-1">steel-*</code>, legacy) of groen/rood als decoratie.</li>
                        <li class="flex gap-2"><span class="mt-2 h-1 w-1 shrink-0 rounded-full bg-red-500"></span>Middots en em-dashes in teksten: scheid metadata met komma's en gebruik gewone streepjes. Geldt ook voor alles wat Nova schrijft.</li>
                        <li class="flex gap-2"><span class="mt-2 h-1 w-1 shrink-0 rounded-full bg-red-500"></span>Grijs op grijs: elk scherm heeft minstens één warm element.</li>
                    </ul>
                </div>
            </div>
        </section>

        {{-- 8. Layout, laden & beweging --}}
        <section>
            <p class="text-[11px] font-bold tracking-widest text-slate-500 uppercase">08. Layout, laden &amp; beweging</p>
            <h2 class="mt-1 mb-4 text-xl font-bold text-white">Snel voelen, rustig bewegen</h2>

            <div class="space-y-4">
                {{-- Layoutprincipe --}}
                <div class="rounded-2xl border border-surface-700 bg-surface-800 p-6">
                    <p class="mb-1 text-sm font-bold text-white">Paginaopbouw - hero-band, strip, werklijst</p>
                    <p class="mb-4 text-xs text-slate-500">Overzichtspagina's volgen één ritme: een donkere hero-band met context bovenaan, een compacte cijferstrip, daaronder de werklijst prominent links en ondersteuning rechts. Actie gaat vóór informatie.</p>
                    <div class="space-y-1.5">
                        <div class="flex h-10 items-center rounded-lg bg-navy-950 px-3 text-[11px] font-bold text-white">Hero-band: begroeting + Nova + aandacht</div>
                        <div class="grid grid-cols-5 gap-1.5">
                            <div class="flex h-7 items-center justify-center rounded-lg bg-project-500/15 text-[10px] font-bold text-project-300">stat</div>
                            <div class="flex h-7 items-center justify-center rounded-lg bg-lead-500/15 text-[10px] font-bold text-lead-300">stat</div>
                            <div class="flex h-7 items-center justify-center rounded-lg bg-offerte-500/15 text-[10px] font-bold text-offerte-300">stat</div>
                            <div class="flex h-7 items-center justify-center rounded-lg bg-planning-500/15 text-[10px] font-bold text-planning-300">stat</div>
                            <div class="flex h-7 items-center justify-center rounded-lg bg-surface-750 text-[10px] font-bold text-slate-400">stat</div>
                        </div>
                        <div class="grid grid-cols-3 gap-1.5">
                            <div class="col-span-2 flex h-16 items-center justify-center rounded-lg border border-surface-700 bg-surface-800 text-[11px] font-bold text-slate-400">Werklijst (nu doen)</div>
                            <div class="flex h-16 items-center justify-center rounded-lg border border-surface-700 bg-surface-850 text-center text-[11px] font-bold text-slate-400">Planning + snel</div>
                        </div>
                        <div class="flex h-9 items-center justify-center rounded-lg border border-surface-700 bg-surface-800 text-[11px] font-bold text-slate-400">Projecten, vier op een rij</div>
                    </div>
                </div>

                {{-- Skeleton loaders --}}
                <div class="rounded-2xl border border-surface-700 bg-surface-800 p-6">
                    <p class="mb-1 text-sm font-bold text-white">Skeleton loaders - geen spinners</p>
                    <p class="mb-4 text-xs text-slate-500">Alles wat async laadt (Nova-briefing, chatberichten, zoekresultaten) toont een shimmer in de vorm van de content die komt: <code class="rounded bg-surface-750 px-1">&lt;x-skeleton&gt;</code>, of <code class="rounded bg-surface-750 px-1">.skeleton</code> op een eigen blokje. Op donkere kaarten de <code class="rounded bg-surface-750 px-1">dark</code>-variant.</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-xl border border-surface-700 p-4">
                            <x-skeleton :lines="3" avatar />
                        </div>
                        <div class="rounded-xl bg-navy-950 p-4">
                            <x-skeleton :lines="3" avatar dark />
                        </div>
                    </div>
                </div>

                {{-- Paginaovergangen --}}
                <div class="rounded-2xl border border-surface-700 bg-surface-800 p-6">
                    <p class="mb-1 text-sm font-bold text-white">Naadloze paginaovergangen</p>
                    <p class="mb-3 text-xs text-slate-500">Navigeren voelt als één omgeving, zonder witte flits - je merkt het op elke pagina, ook deze.</p>
                    <ul class="space-y-2 text-sm text-slate-200">
                        <li class="flex gap-2"><span class="mt-2 h-1 w-1 shrink-0 rounded-full bg-brand-500"></span><span><span class="font-semibold text-white">View transitions:</span> de oude pagina blijft staan tot de nieuwe klaar is en vloeit er zacht in over (cross-document, puur CSS in <code class="rounded bg-surface-750 px-1">app.css</code>).</span></li>
                        <li class="flex gap-2"><span class="mt-2 h-1 w-1 shrink-0 rounded-full bg-brand-500"></span><span><span class="font-semibold text-white">Vooraf laden bij hover:</span> Chrome laadt een pagina al in zodra je de muis op een link legt (speculation rules in de layout) - klikken voelt direct. Chat en de offerte-builder zijn uitgesloten.</span></li>
                        <li class="flex gap-2"><span class="mt-2 h-1 w-1 shrink-0 rounded-full bg-brand-500"></span><span><span class="font-semibold text-white">Rustig bewegen:</span> overgangen zijn kort (±150 ms) en subtiel; bij <code class="rounded bg-surface-750 px-1">prefers-reduced-motion</code> staat alle beweging uit, ook de shimmer.</span></li>
                    </ul>
                </div>
            </div>
        </section>
    </div>

</x-layouts.app>
