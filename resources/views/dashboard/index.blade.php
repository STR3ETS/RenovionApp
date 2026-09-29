<x-layouts.app title="Vandaag">

    {{-- Begroeting (mockup §18: licht, boven de kaarten) --}}
    <section class="mb-5">
        <h1 class="text-2xl font-bold text-navy-900 lg:text-3xl">
            {{ now()->hour < 12 ? 'Goedemorgen' : (now()->hour < 18 ? 'Goedemiddag' : 'Goedenavond') }} {{ str(auth()->user()->name)->before(' ') }}.
        </h1>
        <p class="mt-1 text-sm text-gray-500">{{ now()->translatedFormat('l j F') }} — hier is de status van je projecten en aandachtspunten.</p>
    </section>

    @php $quotesEnabled = config('renovion.modules.quotes'); @endphp
    <div class="grid gap-x-4 gap-y-6 lg:grid-cols-3">

        {{-- Kerncijfers (mockup §18: statcards met subregel) --}}
        <section class="grid grid-cols-2 gap-2 lg:col-span-2 lg:gap-3 xl:grid-cols-4">
            <x-stat-tile label="Actieve projecten" :value="$stats['actieve_projecten']" icon="building-office" :href="route('projects.index')"
                         :sub="$stats['projecten_aandacht'] > 0 ? $stats['projecten_aandacht'].' '.($stats['projecten_aandacht'] === 1 ? 'vraagt aandacht' : 'vragen aandacht') : 'alles op schema'"
                         :alert="$stats['projecten_aandacht'] > 0" />
            <x-stat-tile label="Nieuwe aanvragen" :value="$stats['nieuwe_aanvragen']" icon="inbox" :href="route('leads.index')"
                         :sub="$stats['open_aanvragen'].' open in de pipeline'" />
            @if ($quotesEnabled)
                <x-stat-tile label="Open offertes" :value="$stats['open_offertes']" icon="document-text" :href="route('quotes.index')" />
            @endif
            <x-stat-tile label="Taken deze week" :value="$stats['taken_deze_week']" icon="clipboard-check" :href="route('tasks.index')"
                         :sub="$stats['taken_te_laat'] > 0 ? $stats['taken_te_laat'].' te laat' : 'niets te laat'"
                         :alert="$stats['taken_te_laat'] > 0" />
            <x-stat-tile label="Omzet (lopend)" :value="'€ '.number_format($stats['omzet_lopend'], 0, ',', '.')" icon="currency-euro" :href="route('projects.index')"
                         :sub="'€ '.number_format($stats['omzet_open'], 0, ',', '.').' nog open'" />
        </section>

        {{-- Rechterkolom: Nova op natuurlijke hoogte + vandaag gepland --}}
        <div class="space-y-4 lg:row-span-3">
        {{-- Nova-kaart (mockup §18: donkere kaart met dagbriefing + signalen) --}}
        <section class="rounded-2xl bg-navy-950 p-5 text-white">
            <div class="flex items-center gap-3">
                <x-nova-avatar class="h-10 w-10 ring-2 ring-brand-500" />
                <p class="flex-1 text-base font-bold">Nova</p>
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
                <div class="mt-4 space-y-3 border-t border-navy-800 pt-4">
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
                <a href="{{ route('attention.index') }}" class="block rounded-xl bg-navy-800 py-2.5 text-center text-sm font-semibold text-white transition hover:bg-navy-700">
                    Bekijk alle inzichten →
                </a>
            </div>
        </section>

        {{-- Vandaag gepland (compact naast Nova) --}}
        <section class="rounded-2xl border border-gray-200 bg-white p-5">
            <h2 class="mb-3 text-sm font-bold text-navy-900">Vandaag gepland</h2>
            @if ($planningVandaag->isEmpty())
                <p class="text-sm text-gray-400">Niets gepland voor vandaag.</p>
            @else
                <div class="space-y-2">
                    @foreach ($planningVandaag as $entry)
                        <div class="flex items-center gap-3 rounded-xl border p-2.5 text-sm {{ $entry->type->blockClasses() }}">
                            <span class="w-14 shrink-0 text-xs font-bold">
                                {{ $entry->start_time ? substr($entry->start_time, 0, 5) : 'hele dag' }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-semibold">{{ $entry->displayTitle() }}</span>
                                <span class="block truncate text-xs opacity-70">{{ $entry->user->name }} · {{ $entry->type->label() }}</span>
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
        </div>

        {{-- Recente projecten (mockup §18: fotokaarten met voortgang) --}}
        @if ($recenteProjecten->isNotEmpty())
            <section class="lg:col-span-2">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-bold text-navy-900">Recente projecten</h2>
                    <a href="{{ route('projects.index') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-500">Alle projecten →</a>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @foreach ($recenteProjecten as $project)
                        <x-project-card :project="$project" />
                    @endforeach
                </div>
            </section>
        @endif

    {{-- Nu doen --}}
    <section class="lg:col-span-2">
        <h2 class="mb-3 text-base font-bold text-navy-900">Nu doen</h2>

        @if ($aandachtTotaal === 0)
            <x-empty-state title="Niets dat nu aandacht vraagt" subtitle="Nieuwe signalen verschijnen hier vanzelf." />
        @else
            <div class="space-y-2">
                @foreach ($betaalRisicos as $project)
                    <a href="{{ route('projects.show', $project) }}" class="flex items-center gap-3 rounded-xl border border-red-200 bg-white p-3 transition hover:border-red-400">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600"><x-icon name="currency-euro" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-navy-900">{{ $project->customer->name }}: aanbetaling nog niet ontvangen</span>
                            <span class="block text-xs text-gray-500">
                                € {{ number_format((float) $project->deposit_amount, 0, ',', '.') }}
                                · start {{ $project->start_date->translatedFormat('j M') }}
                            </span>
                        </span>
                        <x-signal-dot color="red" />
                    </a>
                @endforeach

                @foreach ($opvolgOffertes as $quote)
                    <a href="{{ route('quotes.show', $quote) }}" class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-3 transition hover:border-brand-400">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-600"><x-icon name="document-text" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-navy-900">{{ $quote->customer->name }}: offerte {{ $quote->daysOpen() }} dagen open</span>
                            <span class="block text-xs text-gray-500">
                                € {{ number_format((float) $quote->total, 0, ',', '.') }}
                                @if ($quote->viewed_count > 0) · {{ $quote->viewed_count }}× bekeken @endif
                            </span>
                        </span>
                        <x-signal-dot :color="$quote->daysOpen() >= 5 ? 'red' : 'amber'" />
                    </a>
                @endforeach

                @foreach ($opvolgLeads as $lead)
                    <a href="{{ route('leads.show', $lead) }}" class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-3 transition hover:border-brand-400">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-steel-100 text-steel-600"><x-icon name="phone" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-navy-900">
                                {{ $lead->customer->name }}:
                                {{ $lead->next_action ?? ($lead->status === \App\Enums\LeadStatus::Nieuw ? 'nieuwe aanvraag opvolgen' : 'opvolgen') }}
                            </span>
                            <span class="block text-xs text-gray-500">
                                {{ $lead->service ?? 'Aanvraag' }}
                                @if ($lead->next_action_at) · {{ $lead->next_action_at->translatedFormat('j M H:i') }} @endif
                            </span>
                        </span>
                        <x-status-badge :status="$lead->status" />
                    </a>
                @endforeach

                @foreach ($taken as $task)
                    <div class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-3">
                        <button
                            x-data
                            @click="patchJson('{{ route('tasks.status', $task) }}', { status: 'afgerond' }).then(() => window.location.reload())"
                            class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2 border-gray-300 transition hover:border-green-500 hover:bg-green-50"
                            title="Afronden">
                        </button>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-navy-900 {{ $task->isOverdue() ? 'text-red-700' : '' }}">{{ $task->title }}</span>
                            <span class="block text-xs text-gray-500">
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

    {{-- Quick actions --}}
    <section class="lg:col-span-2">
        <h2 class="mb-3 text-base font-bold text-navy-900">Snel</h2>
        <div class="grid grid-cols-2 gap-2 {{ $quotesEnabled ? 'lg:grid-cols-5' : 'lg:grid-cols-4' }}">
            <a href="{{ route('leads.create') }}" class="rounded-xl bg-brand-600 px-4 py-3 text-center text-sm font-semibold text-white transition hover:bg-brand-500">+ Aanvraag</a>
            @if ($quotesEnabled)
                <a href="{{ route('quotes.create') }}" class="rounded-xl bg-navy-900 px-4 py-3 text-center text-sm font-semibold text-white transition hover:bg-navy-800">+ Offerte</a>
            @endif
            <a href="{{ route('projects.create') }}" class="rounded-xl bg-navy-900 px-4 py-3 text-center text-sm font-semibold text-white transition hover:bg-navy-800">+ Project</a>
            <a href="{{ route('planning.index') }}" class="rounded-xl border border-gray-300 bg-white px-4 py-3 text-center text-sm font-semibold text-navy-900 transition hover:border-brand-400">Planning</a>
            <a href="{{ route('tasks.index') }}" class="rounded-xl border border-gray-300 bg-white px-4 py-3 text-center text-sm font-semibold text-navy-900 transition hover:border-brand-400">Taken</a>
        </div>
    </section>
    </div>

</x-layouts.app>
