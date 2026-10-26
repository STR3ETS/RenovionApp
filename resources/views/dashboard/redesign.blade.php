<x-layouts.app title="Redesign: Vandaag">

    {{-- Conceptbanner --}}
    <section class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-dashed border-gray-300 bg-white px-4 py-3">
        <p class="flex items-center gap-2 text-sm text-gray-600">
            <x-icon name="eye" class="h-4 w-4 shrink-0 text-brand-500" />
            <span><span class="font-bold text-navy-900">Ontwerpvoorstel</span> — zo ziet Vandaag eruit in de nieuwe stijl. Het echte dashboard verandert pas na akkoord.</span>
        </p>
        <a href="{{ route('styleguide') }}" class="text-sm font-semibold text-brand-600 transition hover:text-brand-500">Bekijk de stijlgids →</a>
    </section>

    {{-- Begroeting --}}
    <section class="mb-6">
        <p class="text-[11px] font-bold tracking-widest text-gray-400 uppercase">{{ now()->translatedFormat('l j F') }}</p>
        <h1 class="mt-1 text-2xl font-bold text-navy-950 lg:text-3xl">
            {{ now()->hour < 12 ? 'Goedemorgen' : (now()->hour < 18 ? 'Goedemiddag' : 'Goedenavond') }} {{ str(auth()->user()->name)->before(' ') }}.
        </h1>
        <p class="mt-1 text-sm text-gray-500">
            @if ($aandachtTotaal > 0)
                {{ $aandachtTotaal === 1 ? 'Eén zaak vraagt' : $aandachtTotaal.' zaken vragen' }} vandaag je aandacht — Nova heeft ze voor je klaargezet.
            @else
                Alles loopt op schema. Geen openstaande acties voor vandaag.
            @endif
        </p>
    </section>

    @php $quotesEnabled = config('renovion.modules.quotes'); @endphp
    <div class="grid gap-x-4 gap-y-6 lg:grid-cols-3 lg:grid-rows-[auto_auto_auto_1fr]">

        {{-- Kerncijfers: zachte categorietint + gevulde chip --}}
        <section class="grid grid-cols-2 gap-2 lg:col-span-2 lg:gap-3 {{ $quotesEnabled ? 'xl:grid-cols-5' : 'xl:grid-cols-4' }}">
            <a href="{{ route('projects.index') }}" class="block rounded-2xl border border-project-200 bg-project-50 p-4 transition hover:shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-xs font-semibold text-project-700">Actieve projecten</p>
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-project-500 text-white"><x-icon name="building-office" class="h-4 w-4" /></span>
                </div>
                <p class="mt-1 text-2xl font-bold text-navy-950 lg:text-3xl">{{ $stats['actieve_projecten'] }}</p>
                <p class="mt-0.5 text-xs font-medium {{ $stats['projecten_aandacht'] > 0 ? 'text-red-600' : 'text-project-700/60' }}">
                    {{ $stats['projecten_aandacht'] > 0 ? $stats['projecten_aandacht'].' '.($stats['projecten_aandacht'] === 1 ? 'vraagt aandacht' : 'vragen aandacht') : 'alles op schema' }}
                </p>
            </a>
            <a href="{{ route('leads.index') }}" class="block rounded-2xl border border-lead-200 bg-lead-50 p-4 transition hover:shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-xs font-semibold text-lead-700">Nieuwe aanvragen</p>
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-lead-500 text-white"><x-icon name="inbox" class="h-4 w-4" /></span>
                </div>
                <p class="mt-1 text-2xl font-bold text-navy-950 lg:text-3xl">{{ $stats['nieuwe_aanvragen'] }}</p>
                <p class="mt-0.5 text-xs font-medium text-lead-700/60">{{ $stats['open_aanvragen'] }} open in de pipeline</p>
            </a>
            @if ($quotesEnabled)
                <a href="{{ route('quotes.index') }}" class="block rounded-2xl border border-offerte-200 bg-offerte-50 p-4 transition hover:shadow-sm">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-xs font-semibold text-offerte-700">Open offertes</p>
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-offerte-500 text-white"><x-icon name="document-text" class="h-4 w-4" /></span>
                    </div>
                    <p class="mt-1 text-2xl font-bold text-navy-950 lg:text-3xl">{{ $stats['open_offertes'] }}</p>
                    <p class="mt-0.5 text-xs font-medium text-offerte-700/60">wachten op de klant</p>
                </a>
            @endif
            <a href="{{ route('tasks.index') }}" class="block rounded-2xl border border-planning-200 bg-planning-50 p-4 transition hover:shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-xs font-semibold text-planning-700">Taken deze week</p>
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-planning-500 text-white"><x-icon name="clipboard-check" class="h-4 w-4" /></span>
                </div>
                <p class="mt-1 text-2xl font-bold text-navy-950 lg:text-3xl">{{ $stats['taken_deze_week'] }}</p>
                <p class="mt-0.5 text-xs font-medium {{ $stats['taken_te_laat'] > 0 ? 'text-red-600' : 'text-planning-700/60' }}">
                    {{ $stats['taken_te_laat'] > 0 ? $stats['taken_te_laat'].' te laat' : 'niets te laat' }}
                </p>
            </a>
            <a href="{{ route('projects.index') }}" class="block rounded-2xl border border-gray-200 bg-white p-4 transition hover:shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-xs font-semibold text-gray-500">Omzet (lopend)</p>
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-navy-950 text-white"><x-icon name="currency-euro" class="h-4 w-4" /></span>
                </div>
                <p class="mt-1 text-2xl font-bold text-navy-950 lg:text-3xl">€ {{ number_format($stats['omzet_lopend'], 0, ',', '.') }}</p>
                <p class="mt-0.5 text-xs font-medium text-gray-400">€ {{ number_format($stats['omzet_open'], 0, ',', '.') }} nog open</p>
            </a>
        </section>

        {{-- Rechterkolom: Nova + Nu doen --}}
        <div class="space-y-4 lg:row-span-4">

            {{-- Nova: het donkere anker --}}
            <section class="rounded-2xl bg-gradient-to-br from-navy-950 to-navy-900 p-5 text-white">
                <div class="flex items-center gap-3">
                    <x-nova-avatar class="h-10 w-10 ring-2 ring-brand-500" />
                    <div class="flex-1">
                        <p class="text-base font-bold">Nova</p>
                        <p class="text-[11px] font-medium text-navy-300">Jouw dagstart</p>
                    </div>
                    @if ($attentionCount > 0)
                        <a href="{{ route('attention.index') }}" class="flex h-6 min-w-6 items-center justify-center rounded-full bg-brand-500 px-1.5 text-xs font-bold text-white">{{ $attentionCount }}</a>
                    @endif
                </div>

                <div class="mt-3" x-data="{
                        briefing: null,
                        loading: false,
                        async load(refresh = false) {
                            this.loading = true;
                            try {
                                const response = await fetch('{{ route('nova.briefing') }}' + (refresh ? '?refresh=1' : ''), { headers: { 'Accept': 'application/json' } });
                                this.briefing = (await response.json()).text;
                            } catch (error) { /* fallback blijft staan */ }
                            this.loading = false;
                        },
                     }" x-init="load()">
                    <p class="text-sm text-navy-100" x-show="!briefing">
                        @if ($aandachtTotaal > 0)
                            Vandaag {{ $aandachtTotaal === 1 ? 'vraagt 1 zaak' : "vragen {$aandachtTotaal} zaken" }} je aandacht.
                        @else
                            Alles loopt op schema. Geen openstaande acties voor vandaag.
                        @endif
                    </p>
                    <p class="text-sm whitespace-pre-line text-navy-100" x-show="briefing" x-text="briefing" x-cloak></p>
                    <button x-show="briefing && !loading" x-cloak @click="load(true)"
                            class="mt-1.5 text-xs font-semibold text-navy-300 transition hover:text-white">
                        Briefing vernieuwen
                    </button>
                    <p x-show="loading" x-cloak class="mt-1 text-xs text-navy-400">Nova stelt de briefing op…</p>
                </div>

                @if ($aandachtItems->isNotEmpty())
                    <div class="mt-4 space-y-3 border-t border-white/10 pt-4">
                        @foreach ($aandachtItems as $item)
                            <a href="{{ $item['url'] }}" class="group flex items-start gap-2.5">
                                <x-signal-dot :color="$item['severity'] === 'rood' ? 'red' : 'amber'" class="mt-1.5" />
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium text-white group-hover:text-brand-300">{{ $item['title'] }}</span>
                                    <span class="block truncate text-xs text-navy-300">{{ $item['label'] }}</span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endif

                <div class="pt-4">
                    <a href="{{ route('attention.index') }}" class="block rounded-xl bg-white/10 py-2.5 text-center text-sm font-semibold text-white transition hover:bg-white/15">
                        Bekijk alle inzichten →
                    </a>
                </div>
            </section>

            {{-- Nu doen: categoriechip + accentlijn + urgentie-dot --}}
            <section>
                <p class="text-[11px] font-bold tracking-widest text-gray-400 uppercase">Vandaag</p>
                <h2 class="mt-0.5 mb-3 text-base font-bold text-navy-950">Nu doen</h2>

                @if ($aandachtTotaal === 0)
                    <div class="rounded-2xl border border-dashed border-gray-300 bg-white p-6 text-center">
                        <span class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-project-100 text-project-600"><x-icon name="check" class="h-5 w-5" /></span>
                        <p class="text-sm font-semibold text-navy-900">Niets dat nu aandacht vraagt</p>
                        <p class="mt-0.5 text-xs text-gray-400">Nieuwe signalen verschijnen hier vanzelf.</p>
                    </div>
                @else
                    <div class="space-y-2">
                        @foreach ($betaalRisicos as $project)
                            <a href="{{ route('projects.show', $project) }}" class="flex items-center gap-3 rounded-xl border border-gray-200 border-l-4 border-l-red-500 bg-white p-3 transition hover:shadow-sm">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-red-100 text-red-600"><x-icon name="currency-euro" class="h-4 w-4" /></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold text-navy-900">{{ $project->customer->name }}: aanbetaling nog niet ontvangen</span>
                                    <span class="block truncate text-xs text-gray-500">
                                        € {{ number_format((float) $project->deposit_amount, 0, ',', '.') }}
                                        · start {{ $project->start_date->translatedFormat('j M') }}
                                    </span>
                                </span>
                                <x-signal-dot color="red" />
                            </a>
                        @endforeach

                        @foreach ($opvolgOffertes as $quote)
                            <a href="{{ route('quotes.show', $quote) }}" class="flex items-center gap-3 rounded-xl border border-gray-200 border-l-4 border-l-offerte-500 bg-white p-3 transition hover:shadow-sm">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-offerte-100 text-offerte-600"><x-icon name="document-text" class="h-4 w-4" /></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold text-navy-900">{{ $quote->customer->name }}: offerte {{ $quote->daysOpen() }} dagen open</span>
                                    <span class="block truncate text-xs text-gray-500">
                                        € {{ number_format((float) $quote->total, 0, ',', '.') }}
                                        @if ($quote->viewed_count > 0) · {{ $quote->viewed_count }}× bekeken @endif
                                    </span>
                                </span>
                                <x-signal-dot :color="$quote->daysOpen() >= 5 ? 'red' : 'amber'" />
                            </a>
                        @endforeach

                        @foreach ($opvolgLeads as $lead)
                            <a href="{{ route('leads.show', $lead) }}" class="flex items-center gap-3 rounded-xl border border-gray-200 border-l-4 border-l-lead-500 bg-white p-3 transition hover:shadow-sm">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-lead-100 text-lead-600"><x-icon name="phone" class="h-4 w-4" /></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold text-navy-900">
                                        {{ $lead->customer->name }}:
                                        {{ $lead->next_action ?? ($lead->status === \App\Enums\LeadStatus::Nieuw ? 'nieuwe aanvraag opvolgen' : 'opvolgen') }}
                                    </span>
                                    <span class="block truncate text-xs text-gray-500">
                                        {{ $lead->service ?? 'Aanvraag' }}
                                        @if ($lead->next_action_at) · {{ $lead->next_action_at->translatedFormat('j M H:i') }} @endif
                                    </span>
                                </span>
                                <x-status-badge :status="$lead->status" />
                            </a>
                        @endforeach

                        @foreach ($taken as $task)
                            <div class="flex items-center gap-3 rounded-xl border border-gray-200 border-l-4 border-l-planning-500 bg-white p-3">
                                <button
                                    x-data
                                    @click="patchJson('{{ route('tasks.status', $task) }}', { status: 'afgerond' }).then(() => window.location.reload())"
                                    class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2 border-gray-300 transition hover:border-project-500 hover:bg-project-50"
                                    title="Afronden">
                                </button>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold {{ $task->isOverdue() ? 'text-red-700' : 'text-navy-900' }}">{{ $task->title }}</span>
                                    <span class="block truncate text-xs text-gray-500">
                                        {{ $task->customer?->name ?? $task->project?->name ?? 'Algemeen' }}
                                        @if ($task->deadline) · {{ $task->deadline->isToday() ? 'vandaag' : $task->deadline->translatedFormat('j M') }} @endif
                                    </span>
                                </span>
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $task->priority->dotClasses() }}" title="Prioriteit: {{ $task->priority->label() }}"></span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

        {{-- Recente projecten --}}
        @if ($recenteProjecten->isNotEmpty())
            <section class="lg:col-span-2">
                <div class="mb-3 flex items-end justify-between">
                    <div>
                        <p class="text-[11px] font-bold tracking-widest text-project-600 uppercase">Projecten</p>
                        <h2 class="mt-0.5 text-base font-bold text-navy-950">Recent in uitvoering</h2>
                    </div>
                    <a href="{{ route('projects.index') }}" class="text-xs font-semibold text-brand-600 transition hover:text-brand-500">Alle projecten →</a>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @foreach ($recenteProjecten as $project)
                        <x-project-card :project="$project" />
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Snel: één oranje actie, de rest herkenbaar aan categorie-icoon --}}
        <section class="lg:col-span-2">
            <p class="text-[11px] font-bold tracking-widest text-gray-400 uppercase">Snel</p>
            <h2 class="mt-0.5 mb-3 text-base font-bold text-navy-950">Direct starten</h2>
            <div class="grid grid-cols-2 gap-2 {{ $quotesEnabled ? 'lg:grid-cols-5' : 'lg:grid-cols-4' }}">
                <a href="{{ route('leads.create') }}" class="flex items-center justify-center gap-2 rounded-xl bg-brand-500 px-4 py-3 text-sm font-bold text-white transition hover:bg-brand-600">
                    <x-icon name="plus" class="h-4 w-4" /> Aanvraag
                </a>
                @if ($quotesEnabled)
                    <a href="{{ route('quotes.create') }}" class="flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-navy-900 transition hover:border-offerte-500 hover:shadow-sm">
                        <span class="flex h-6 w-6 items-center justify-center rounded-md bg-offerte-100 text-offerte-600"><x-icon name="document-text" class="h-3.5 w-3.5" /></span> Offerte
                    </a>
                @endif
                <a href="{{ route('projects.create') }}" class="flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-navy-900 transition hover:border-project-500 hover:shadow-sm">
                    <span class="flex h-6 w-6 items-center justify-center rounded-md bg-project-100 text-project-600"><x-icon name="building-office" class="h-3.5 w-3.5" /></span> Project
                </a>
                <a href="{{ route('planning.index') }}" class="flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-navy-900 transition hover:border-planning-500 hover:shadow-sm">
                    <span class="flex h-6 w-6 items-center justify-center rounded-md bg-planning-100 text-planning-600"><x-icon name="calendar" class="h-3.5 w-3.5" /></span> Planning
                </a>
                <a href="{{ route('tasks.index') }}" class="flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-navy-900 transition hover:border-planning-500 hover:shadow-sm">
                    <span class="flex h-6 w-6 items-center justify-center rounded-md bg-planning-100 text-planning-600"><x-icon name="clipboard-check" class="h-3.5 w-3.5" /></span> Taken
                </a>
            </div>
        </section>

        {{-- Vandaag gepland --}}
        <section class="lg:col-span-2 lg:self-start">
            <p class="text-[11px] font-bold tracking-widest text-planning-600 uppercase">Planning</p>
            <h2 class="mt-0.5 mb-3 text-base font-bold text-navy-950">Vandaag gepland</h2>
            @if ($planningVandaag->isEmpty())
                <div class="rounded-2xl border border-dashed border-gray-300 bg-white p-6 text-center">
                    <span class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-planning-100 text-planning-600"><x-icon name="calendar" class="h-5 w-5" /></span>
                    <p class="text-sm font-semibold text-navy-900">Niets gepland voor vandaag</p>
                </div>
            @else
                <div class="space-y-2">
                    @foreach ($planningVandaag as $entry)
                        <div class="flex items-center gap-3 rounded-xl border bg-white p-3 {{ $entry->type->blockClasses() }}">
                            <span class="w-16 shrink-0 rounded-lg bg-planning-100 py-1 text-center text-xs font-bold text-planning-700">
                                {{ $entry->start_time ? substr($entry->start_time, 0, 5) : 'dag' }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold">{{ $entry->displayTitle() }}</span>
                                <span class="block text-xs opacity-70">{{ $entry->user->name }} · {{ $entry->type->label() }}</span>
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

</x-layouts.app>
