<x-layouts.app title="Vandaag">

    @php $quotesEnabled = config('renovion.modules.quotes'); @endphp

    {{-- Hero-band: begroeting + Nova in één donker anker --}}
    <section class="mb-4 overflow-hidden rounded-2xl bg-gradient-to-br from-navy-950 via-navy-950 to-navy-900 text-white">
        <div class="grid gap-6 p-6 lg:grid-cols-3 lg:p-8">
            <div class="lg:col-span-2">
                <p class="text-[11px] font-bold tracking-widest text-navy-300 uppercase">{{ now()->translatedFormat('l j F') }}</p>
                <h1 class="mt-1 text-2xl font-bold lg:text-3xl">
                    {{ now()->hour < 12 ? 'Goedemorgen' : (now()->hour < 18 ? 'Goedemiddag' : 'Goedenavond') }} {{ str(auth()->user()->name)->before(' ') }}.
                </h1>

                <div class="mt-4 flex items-start gap-3" x-data="{
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
                    <x-nova-avatar class="h-10 w-10 shrink-0 ring-2 ring-brand-500" />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-bold">Nova <span class="font-medium text-navy-300">, jouw dagstart</span></p>

                        {{-- Skeleton zolang de briefing laadt --}}
                        <div x-show="loading && !briefing" class="mt-2 max-w-xl">
                            <x-skeleton :lines="2" dark />
                        </div>

                        <p class="mt-1.5 max-w-xl text-sm leading-relaxed text-navy-100" x-show="!briefing && !loading">
                            @if ($aandachtTotaal > 0)
                                Vandaag {{ $aandachtTotaal === 1 ? 'vraagt 1 zaak' : "vragen {$aandachtTotaal} zaken" }} je aandacht.
                            @else
                                Alles loopt op schema. Geen openstaande acties voor vandaag.
                            @endif
                        </p>
                        <p class="mt-1.5 max-w-xl text-sm leading-relaxed whitespace-pre-line text-navy-100" x-show="briefing" x-text="briefing" x-cloak></p>
                        <button x-show="briefing && !loading" x-cloak @click="load(true)"
                                class="mt-1.5 text-xs font-semibold text-navy-300 transition hover:text-white">
                            Briefing vernieuwen
                        </button>
                    </div>
                </div>
            </div>

            {{-- Aandachtpaneel in de hero --}}
            <div class="rounded-2xl bg-white/5 p-5">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-[11px] font-bold tracking-widest text-navy-300 uppercase">Aandacht</p>
                    <span class="flex h-6 min-w-6 items-center justify-center rounded-full {{ $attentionCount > 0 ? 'bg-brand-500' : 'bg-white/10' }} px-1.5 text-xs font-bold text-white">{{ $attentionCount }}</span>
                </div>
                @if ($aandachtItems->isNotEmpty())
                    <div class="mt-3 space-y-2.5">
                        @foreach ($aandachtItems->take(3) as $item)
                            <a href="{{ $item['url'] }}" class="group flex items-start gap-2.5">
                                <x-signal-dot :color="$item['severity'] === 'rood' ? 'red' : 'amber'" class="mt-1.5" />
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium text-white group-hover:text-brand-300">{{ $item['title'] }}</span>
                                    <span class="block truncate text-xs text-navy-300">{{ $item['label'] }}</span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                @else
                    <p class="mt-3 text-sm text-navy-200">Geen uitzonderingen - alles loopt zoals gepland.</p>
                @endif
                <a href="{{ route('attention.index') }}" class="mt-4 block rounded-xl bg-brand-500 py-2.5 text-center text-sm font-bold text-white transition hover:bg-brand-600">
                    Bekijk alle inzichten →
                </a>
            </div>
        </div>
    </section>

    {{-- Statstrip: compacte tegels, categoriekleur als chip --}}
    <section class="mb-6 grid grid-cols-2 gap-2 lg:gap-3 {{ $quotesEnabled ? 'xl:grid-cols-5' : 'xl:grid-cols-4' }}">
        <a href="{{ route('projects.index') }}" class="flex items-center gap-3 rounded-2xl border border-project-200 bg-project-50 p-3.5 transition hover:shadow-sm">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-project-500 text-white"><x-icon name="building-office" class="h-5 w-5" /></span>
            <span class="min-w-0">
                <span class="block text-xl leading-tight font-bold text-navy-950">{{ $stats['actieve_projecten'] }}</span>
                <span class="block truncate text-xs font-medium {{ $stats['projecten_aandacht'] > 0 ? 'text-red-600' : 'text-project-700/70' }}">
                    {{ $stats['projecten_aandacht'] > 0 ? 'projecten, '.$stats['projecten_aandacht'].' aandacht' : 'projecten op schema' }}
                </span>
            </span>
        </a>
        <a href="{{ route('leads.index') }}" class="flex items-center gap-3 rounded-2xl border border-lead-200 bg-lead-50 p-3.5 transition hover:shadow-sm">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-lead-500 text-white"><x-icon name="inbox" class="h-5 w-5" /></span>
            <span class="min-w-0">
                <span class="block text-xl leading-tight font-bold text-navy-950">{{ $stats['nieuwe_aanvragen'] }}</span>
                <span class="block truncate text-xs font-medium text-lead-700/70">nieuwe aanvragen, {{ $stats['open_aanvragen'] }} open</span>
            </span>
        </a>
        @if ($quotesEnabled)
            <a href="{{ route('quotes.index') }}" class="flex items-center gap-3 rounded-2xl border border-offerte-200 bg-offerte-50 p-3.5 transition hover:shadow-sm">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-offerte-500 text-white"><x-icon name="document-text" class="h-5 w-5" /></span>
                <span class="min-w-0">
                    <span class="block text-xl leading-tight font-bold text-navy-950">{{ $stats['open_offertes'] }}</span>
                    <span class="block truncate text-xs font-medium text-offerte-700/70">offertes bij de klant</span>
                </span>
            </a>
        @endif
        <a href="{{ route('tasks.index') }}" class="flex items-center gap-3 rounded-2xl border border-planning-200 bg-planning-50 p-3.5 transition hover:shadow-sm">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-planning-500 text-white"><x-icon name="clipboard-check" class="h-5 w-5" /></span>
            <span class="min-w-0">
                <span class="block text-xl leading-tight font-bold text-navy-950">{{ $stats['taken_deze_week'] }}</span>
                <span class="block truncate text-xs font-medium {{ $stats['taken_te_laat'] > 0 ? 'text-red-600' : 'text-planning-700/70' }}">
                    {{ $stats['taken_te_laat'] > 0 ? 'taken, '.$stats['taken_te_laat'].' te laat' : 'taken deze week' }}
                </span>
            </span>
        </a>
        <a href="{{ route('projects.index') }}" class="flex items-center gap-3 rounded-2xl border border-gray-200 bg-white p-3.5 transition hover:shadow-sm">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-navy-950 text-white"><x-icon name="currency-euro" class="h-5 w-5" /></span>
            <span class="min-w-0">
                <span class="block text-xl leading-tight font-bold text-navy-950">€ {{ number_format($stats['omzet_lopend'], 0, ',', '.') }}</span>
                <span class="block truncate text-xs font-medium text-gray-400">€ {{ number_format($stats['omzet_open'], 0, ',', '.') }} open</span>
            </span>
        </a>
    </section>

    <div class="grid gap-x-4 gap-y-6 lg:grid-cols-3">

        {{-- Werklijst centraal: actie gaat vóór informatie --}}
        <section class="lg:col-span-2">
            <div class="mb-3 flex items-end justify-between">
                <div>
                    <p class="text-[11px] font-bold tracking-widest text-gray-400 uppercase">Vandaag</p>
                    <h2 class="mt-0.5 text-base font-bold text-navy-950">Nu doen</h2>
                </div>
                @if ($aandachtTotaal > 0)
                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-500">{{ $aandachtTotaal }} {{ $aandachtTotaal === 1 ? 'actie' : 'acties' }}</span>
                @endif
            </div>

            @if ($aandachtTotaal === 0)
                <div class="rounded-2xl border border-dashed border-gray-300 bg-white p-8 text-center">
                    <span class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-project-100 text-project-600"><x-icon name="check" class="h-5 w-5" /></span>
                    <p class="text-sm font-semibold text-navy-900">Niets dat nu aandacht vraagt</p>
                    <p class="mt-0.5 text-xs text-gray-400">Nieuwe signalen verschijnen hier vanzelf.</p>
                </div>
            @else
                <div class="space-y-2">
                    @foreach ($betaalRisicos as $project)
                        <a href="{{ route('projects.show', $project) }}" class="flex items-center gap-3 rounded-xl border border-gray-200 border-l-4 border-l-red-500 bg-white p-3 transition hover:shadow-sm">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-red-100 text-red-600"><x-icon name="currency-euro" class="h-4 w-4" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-navy-900">{{ $project->customer->name }}: aanbetaling nog niet ontvangen</span>
                                <span class="block truncate text-xs text-gray-500">
                                    € {{ number_format((float) $project->deposit_amount, 0, ',', '.') }}
                                   , start {{ $project->start_date->translatedFormat('j M') }}
                                </span>
                            </span>
                            <x-signal-dot color="red" />
                        </a>
                    @endforeach

                    @foreach ($opvolgOffertes as $quote)
                        <a href="{{ route('quotes.show', $quote) }}" class="flex items-center gap-3 rounded-xl border border-gray-200 border-l-4 border-l-offerte-500 bg-white p-3 transition hover:shadow-sm">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-offerte-100 text-offerte-600"><x-icon name="document-text" class="h-4 w-4" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-navy-900">{{ $quote->customer->name }}: offerte {{ $quote->daysOpen() }} dagen open</span>
                                <span class="block truncate text-xs text-gray-500">
                                    € {{ number_format((float) $quote->total, 0, ',', '.') }}
                                    @if ($quote->viewed_count > 0), {{ $quote->viewed_count }}× bekeken @endif
                                </span>
                            </span>
                            <x-signal-dot :color="$quote->daysOpen() >= 5 ? 'red' : 'amber'" />
                        </a>
                    @endforeach

                    @foreach ($opvolgLeads as $lead)
                        <a href="{{ route('leads.show', $lead) }}" class="flex items-center gap-3 rounded-xl border border-gray-200 border-l-4 border-l-lead-500 bg-white p-3 transition hover:shadow-sm">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-lead-100 text-lead-600"><x-icon name="phone" class="h-4 w-4" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-navy-900">
                                    {{ $lead->customer->name }}:
                                    {{ $lead->next_action ?? ($lead->status === \App\Enums\LeadStatus::Nieuw ? 'nieuwe aanvraag opvolgen' : 'opvolgen') }}
                                </span>
                                <span class="block truncate text-xs text-gray-500">
                                    {{ $lead->service ?? 'Aanvraag' }}
                                    @if ($lead->next_action_at), {{ $lead->next_action_at->translatedFormat('j M H:i') }} @endif
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
                                    @if ($task->deadline), {{ $task->deadline->isToday() ? 'vandaag' : $task->deadline->translatedFormat('j M') }} @endif
                                </span>
                            </span>
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $task->priority->dotClasses() }}" title="Prioriteit: {{ $task->priority->label() }}"></span>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Rechterkolom: planning als tijdlijn + snel starten --}}
        <div class="space-y-6">
            <section>
                <p class="text-[11px] font-bold tracking-widest text-planning-600 uppercase">Planning</p>
                <h2 class="mt-0.5 mb-3 text-base font-bold text-navy-950">Vandaag gepland</h2>
                @if ($planningVandaag->isEmpty())
                    <div class="rounded-2xl border border-dashed border-gray-300 bg-white p-6 text-center">
                        <span class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-planning-100 text-planning-600"><x-icon name="calendar" class="h-5 w-5" /></span>
                        <p class="text-sm font-semibold text-navy-900">Niets gepland voor vandaag</p>
                    </div>
                @else
                    <div class="rounded-2xl border border-gray-200 bg-white p-4">
                        <div class="relative space-y-4 border-l-2 border-planning-200 pl-4">
                            @foreach ($planningVandaag as $entry)
                                <div class="relative">
                                    <span class="absolute top-1.5 -left-[1.4rem] h-2.5 w-2.5 rounded-full border-2 border-white bg-planning-500"></span>
                                    <p class="text-xs font-bold text-planning-700">{{ $entry->start_time ? substr($entry->start_time, 0, 5) : 'Hele dag' }}</p>
                                    <p class="truncate text-sm font-semibold text-navy-900">{{ $entry->displayTitle() }}</p>
                                    <p class="truncate text-xs text-gray-500">{{ $entry->user->name }}, {{ $entry->type->label() }}</p>
                                </div>
                            @endforeach
                        </div>
                        <a href="{{ route('planning.index') }}" class="mt-4 block text-xs font-semibold text-brand-600 transition hover:text-brand-500">Naar de planning →</a>
                    </div>
                @endif
            </section>

            <section>
                <p class="text-[11px] font-bold tracking-widest text-gray-400 uppercase">Snel</p>
                <h2 class="mt-0.5 mb-3 text-base font-bold text-navy-950">Direct starten</h2>
                <div class="grid grid-cols-2 gap-2">
                    <a href="{{ route('leads.create') }}" class="col-span-2 flex items-center justify-center gap-2 rounded-xl bg-brand-500 px-4 py-3 text-sm font-bold text-white transition hover:bg-brand-600">
                        <x-icon name="plus" class="h-4 w-4" /> Nieuwe aanvraag
                    </a>
                    @if ($quotesEnabled)
                        <a href="{{ route('quotes.create') }}" class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm font-semibold text-navy-900 transition hover:border-offerte-500 hover:shadow-sm">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-offerte-100 text-offerte-600"><x-icon name="document-text" class="h-3.5 w-3.5" /></span> Offerte
                        </a>
                    @endif
                    <a href="{{ route('projects.create') }}" class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm font-semibold text-navy-900 transition hover:border-project-500 hover:shadow-sm">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-project-100 text-project-600"><x-icon name="building-office" class="h-3.5 w-3.5" /></span> Project
                    </a>
                    <a href="{{ route('planning.index') }}" class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm font-semibold text-navy-900 transition hover:border-planning-500 hover:shadow-sm">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-planning-100 text-planning-600"><x-icon name="calendar" class="h-3.5 w-3.5" /></span> Planning
                    </a>
                    <a href="{{ route('tasks.index') }}" class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm font-semibold text-navy-900 transition hover:border-planning-500 hover:shadow-sm">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-planning-100 text-planning-600"><x-icon name="clipboard-check" class="h-3.5 w-3.5" /></span> Taken
                    </a>
                </div>
            </section>
        </div>

        {{-- Projecten onderaan, vier op een rij --}}
        @if ($recenteProjecten->isNotEmpty())
            <section class="lg:col-span-3">
                <div class="mb-3 flex items-end justify-between">
                    <div>
                        <p class="text-[11px] font-bold tracking-widest text-project-600 uppercase">Projecten</p>
                        <h2 class="mt-0.5 text-base font-bold text-navy-950">Recent in uitvoering</h2>
                    </div>
                    <a href="{{ route('projects.index') }}" class="text-xs font-semibold text-brand-600 transition hover:text-brand-500">Alle projecten →</a>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($recenteProjecten as $project)
                        <x-project-card :project="$project" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>

</x-layouts.app>
