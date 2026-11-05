<x-layouts.app title="Vandaag">

    {{-- Vandaag in de iOS/Android-app (prototype Renovion OS). De website gebruikt dashboard/index. --}}
    <section data-enter class="app-band mb-4">
        <img src="{{ asset('images/os-hero.jpg') }}" alt="" class="bimg">
        <div>
            <p class="eyebrow">{{ now()->translatedFormat('l j F Y') }}</p>
            <h1 class="mt-1.5">
                {{ now()->hour < 12 ? 'Goedemorgen' : (now()->hour < 18 ? 'Goedemiddag' : 'Goedenavond') }}, <span>{{ str(auth()->user()->name)->before(' ') }}</span>
            </h1>
            <span class="rule"></span>
            <p class="lede mt-3">Van aanvraag tot nazorg, alles in één systeem.</p>
        </div>
    </section>

    <div data-enter class="app-novaline mb-3" x-data="{
            briefing: null,
            async load() {
                try {
                    const response = await fetch('{{ route('nova.briefing') }}', { headers: { 'Accept': 'application/json' } });
                    this.briefing = (await response.json()).text;
                } catch (error) { /* fallback blijft staan */ }
            },
         }" x-init="document.documentElement.hasAttribute('data-turbo-preview') || load()">
        <x-nova-avatar class="h-9 w-9 ring-2 ring-brand-500" />
        <p>
            <span x-show="!briefing">
                @if ($attentionCount > 0)
                    Je inbox heeft <b>{{ \App\Support\Tally::of($attentionCount, 'punt', 'punten') }}</b> die aandacht vragen.
                @else
                    Alles loopt op schema. Geen openstaande acties voor vandaag.
                @endif
            </span>
            <span class="whitespace-pre-line" x-show="briefing" x-text="briefing" x-cloak></span>
        </p>
        @if ($attentionCount > 0)
            <a href="{{ route('attention.index') }}" class="app-btn soft">Naar inbox <x-icon name="chevron-right" class="h-3.5 w-3.5" /></a>
        @endif
    </div>

    <section data-fan class="app-kpis mb-3">
        <a href="{{ route('projects.index') }}" class="app-kpi dark">
            <span class="lbl">Werkvoorraad excl. btw</span>
            <span class="v"><i>€</i> <span data-count>{{ number_format($stats['omzet_lopend'], 0, ',', '.') }}</span></span>
            <span class="s">{{ \App\Support\Tally::of($stats['actieve_projecten'], 'project', 'projecten') }} in opdracht</span>
        </a>
        <a href="{{ route('projects.index') }}" class="app-kpi" style="--kc: #3e6ba8">
            <span class="lbl">Lopende projecten</span>
            <span class="v" data-count>{{ $stats['actieve_projecten'] }}</span>
            <span class="s">{{ $stats['projecten_aandacht'] > 0 ? $stats['projecten_aandacht'].' aandacht' : 'Op schema' }}</span>
        </a>
        @if (config('renovion.modules.quotes'))
            <a href="{{ route('quotes.index') }}" class="app-kpi" style="--kc: #1e8a5a">
                <span class="lbl">Open offertes</span>
                <span class="v" data-count>{{ $stats['open_offertes'] }}</span>
                <span class="s">Bij de klant</span>
            </a>
        @endif
        <a href="{{ route('leads.index') }}" class="app-kpi wide" style="--kc: #7c5cd6">
            <span class="lbl">Nieuwe aanvragen</span>
            <span class="v" data-count>{{ $stats['nieuwe_aanvragen'] }}</span>
            <span class="s">{{ $stats['open_aanvragen'] }} open in de pijplijn</span>
        </a>
    </section>

    @if ($aandachtTotaal > 0)
        <section data-enter class="app-card mb-3">
            <div class="app-card-h">
                <h3>Eerst <em>doen</em></h3>
                <a href="{{ route('attention.index') }}" class="app-btn ghost">Alle {{ $attentionCount }} in inbox</a>
            </div>
            <div class="app-card-b">
                @foreach ($opvolgLeads as $lead)
                    <div class="app-li">
                        <span class="app-ic bad"><x-icon name="phone" class="h-[18px] w-[18px]" /></span>
                        <span class="min-w-0 flex-1">
                            <b>{{ $lead->customer->name }}: {{ $lead->next_action ?? ($lead->status === \App\Enums\LeadStatus::Nieuw ? 'nieuwe aanvraag opvolgen' : 'opvolgen') }}</b>
                            <small>{{ collect([$lead->service ?? 'Aanvraag', $lead->next_action_at?->translatedFormat('j M H:i')])->filter()->implode(', ') }}</small>
                            @if ($lead->customer->phone)
                                <a href="tel:{{ $lead->customer->phone }}" class="app-btn">Bellen</a>
                            @else
                                <a href="{{ route('leads.show', $lead) }}" class="app-btn">Bekijk</a>
                            @endif
                        </span>
                    </div>
                @endforeach

                @foreach ($opvolgOffertes as $quote)
                    <div class="app-li">
                        <span class="app-ic or"><x-icon name="document-text" class="h-[18px] w-[18px]" /></span>
                        <span class="min-w-0 flex-1">
                            <b>Offerte {{ $quote->number }} wacht op opvolging</b>
                            <small>{{ $quote->customer->name }}, al {{ \App\Support\Tally::of($quote->daysOpen(), 'dag', 'dagen') }} open</small>
                            <a href="{{ route('quotes.show', $quote) }}" class="app-btn">Bekijk</a>
                        </span>
                    </div>
                @endforeach

                @foreach ($betaalRisicos as $project)
                    <div class="app-li">
                        <span class="app-ic bad"><x-icon name="currency-euro" class="h-[18px] w-[18px]" /></span>
                        <span class="min-w-0 flex-1">
                            <b>Aanbetaling {{ $project->customer->name }} controleren</b>
                            <small>€ {{ number_format((float) $project->deposit_amount, 0, ',', '.') }}, start {{ $project->start_date->translatedFormat('j M') }}</small>
                            <a href="{{ route('projects.show', $project) }}" class="app-btn">Controleer</a>
                        </span>
                    </div>
                @endforeach

                @if ($opvolgLeads->isEmpty() && $opvolgOffertes->isEmpty() && $betaalRisicos->isEmpty())
                    <p class="py-3 text-sm text-gray-500">Geen aanvragen of offertes die nu wachten.</p>
                @endif
            </div>
        </section>
    @endif

    <section data-enter class="app-card mb-3">
        <div class="app-card-h">
            <h3>Jouw <em>dag</em></h3>
            <a href="{{ route('planning.index') }}" class="app-btn ghost">Planning</a>
        </div>
        <div class="app-card-b">
            @forelse ($planningVandaag as $entry)
                <div class="app-li">
                    <span class="min-w-0 flex-1">
                        <span class="mb-1 block font-[family-name:var(--font-display)] font-semibold text-navy-900">{{ $entry->start_time ? substr($entry->start_time, 0, 5) : 'Hele dag' }}</span>
                        <b>{{ $entry->displayTitle() }}</b>
                        <small>{{ $entry->user->name }}, {{ $entry->type->label() }}</small>
                        @if ($entry->project)
                            <a href="{{ route('projects.show', $entry->project) }}" class="app-btn">Dossier</a>
                        @endif
                    </span>
                </div>
            @empty
                <p class="py-3 text-sm text-gray-500">Niets gepland voor vandaag.</p>
            @endforelse
        </div>
    </section>

    <section data-enter class="app-card mb-3">
        <div class="app-card-h">
            <h3>Mijn <em>taken</em></h3>
            <a href="{{ route('tasks.index') }}" class="app-btn ghost">Alle taken</a>
        </div>
        <div class="app-card-b">
            @forelse ($taken as $task)
                <div class="app-li">
                    <span class="app-ic {{ $task->isOverdue() ? 'bad' : 'blue' }}"><x-icon name="clipboard-check" class="h-[18px] w-[18px]" /></span>
                    <span class="min-w-0 flex-1">
                        <b>{{ $task->title }}</b>
                        <small>{{ collect([$task->deadline ? ($task->deadline->isToday() ? 'Vandaag' : $task->deadline->translatedFormat('j M')) : null, $task->customer?->name ?? $task->project?->name])->filter()->implode(', ') }}</small>
                        <button type="button" x-data title="Afronden" aria-label="Taak afronden" class="app-btn"
                                @click="patchJson('{{ route('tasks.status', $task) }}', { status: 'afgerond' }).then(() => window.location.reload())">
                            <x-icon name="check" class="h-4 w-4" />
                        </button>
                    </span>
                </div>
            @empty
                <p class="py-3 text-sm text-gray-500">Geen open taken.</p>
            @endforelse
        </div>
    </section>

    @if ($recenteProjecten->isNotEmpty())
        <section>
            <div class="app-chat-head mt-5 mb-2">
                <h2 class="text-[17px] font-semibold text-navy-900">Recent in uitvoering</h2>
                <a href="{{ route('projects.index') }}" class="app-btn ghost">Alle projecten</a>
            </div>
            <div data-fan class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @foreach ($recenteProjecten as $project)
                    <x-project-card :project="$project" />
                @endforeach
            </div>
        </section>
    @endif

</x-layouts.app>
