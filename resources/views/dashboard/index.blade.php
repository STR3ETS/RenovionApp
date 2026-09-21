<x-layouts.app title="Vandaag">

    {{-- Nova-briefing (fase 1: samenvatting; fase 2: Nova schrijft dit zelf) --}}
    <section class="mb-5 flex items-center gap-4 rounded-2xl bg-navy-900 p-5 text-white">
        <x-nova-avatar class="h-14 w-14 ring-2 ring-brand-500 lg:h-16 lg:w-16" />
        <div class="min-w-0">
            <p class="text-sm text-navy-300">{{ now()->translatedFormat('l j F') }}</p>
            <h1 class="mt-0.5 text-xl font-bold lg:text-2xl">
                {{ now()->hour < 12 ? 'Goedemorgen' : (now()->hour < 18 ? 'Goedemiddag' : 'Goedenavond') }} {{ str(auth()->user()->name)->before(' ') }}.
            </h1>
            <div x-data="{
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
                <p class="mt-0.5 text-sm text-navy-100" x-show="!briefing">
                    @if ($aandachtTotaal > 0)
                        Vandaag {{ $aandachtTotaal === 1 ? 'vraagt 1 zaak' : "vragen {$aandachtTotaal} zaken" }} je aandacht.
                    @else
                        Alles loopt op schema. Geen openstaande acties voor vandaag.
                    @endif
                </p>
                <p class="mt-0.5 text-sm whitespace-pre-line text-navy-100" x-show="briefing" x-text="briefing" x-cloak></p>
                <button x-show="briefing && !loading" x-cloak @click="load(true)"
                        class="mt-1.5 text-xs font-semibold text-navy-300 transition hover:text-white">
                    Briefing vernieuwen
                </button>
                <p x-show="loading" x-cloak class="mt-1 text-xs text-navy-400">Nova stelt de briefing op…</p>
            </div>
        </div>
    </section>

    {{-- Compacte statussen --}}
    @php $quotesEnabled = config('renovion.modules.quotes'); @endphp
    <section class="mb-6 grid grid-cols-3 gap-2 lg:gap-3 {{ $quotesEnabled ? 'lg:grid-cols-6' : 'lg:grid-cols-5' }}">
        <x-stat-tile label="Nieuwe aanvragen" :value="$stats['nieuwe_aanvragen']" :href="route('leads.index')" />
        @if ($quotesEnabled)
            <x-stat-tile label="Open offertes" :value="$stats['open_offertes']" :href="route('quotes.index')" />
        @endif
        <x-stat-tile label="Lopende projecten" :value="$stats['lopende_projecten']" :href="route('projects.index')" />
        <x-stat-tile label="Open taken" :value="$stats['open_taken']" :href="route('tasks.index')" />
        <x-stat-tile label="Planningsrisico's" :value="$stats['planningsrisicos']" :href="route('attention.index')" :alert="true" />
        <x-stat-tile label="Open betalingen" :value="$stats['open_betalingen']" :href="route('attention.index')" :alert="true" />
    </section>

    {{-- Nu doen --}}
    <section class="mb-6">
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
    <section class="mb-6">
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

    {{-- Planning vandaag --}}
    <section>
        <h2 class="mb-3 text-base font-bold text-navy-900">Vandaag gepland</h2>
        @if ($planningVandaag->isEmpty())
            <x-empty-state title="Niets gepland voor vandaag" />
        @else
            <div class="space-y-2">
                @foreach ($planningVandaag as $entry)
                    <div class="flex items-center gap-3 rounded-xl border bg-white p-3 {{ $entry->type->blockClasses() }}">
                        <span class="w-14 shrink-0 text-xs font-bold">
                            {{ $entry->start_time ? substr($entry->start_time, 0, 5) : 'hele dag' }}
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

</x-layouts.app>
