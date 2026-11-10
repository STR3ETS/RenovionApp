<x-layouts.app title="Vandaag">

    @php $quotesEnabled = config('renovion.modules.quotes'); @endphp

    @if ($onboarding)
        {{-- Aan de slag: uitleg en checklist, tot de gebruiker hem uit het menu haalt --}}
        <a href="{{ route('onboarding') }}" class="group mb-4 flex items-center gap-3 rounded-2xl border border-brand-500/30 bg-brand-500/10 p-4 transition hover:border-brand-300 hover:shadow-sm">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-500 text-white"><x-icon name="sparkles" class="h-5 w-5" /></span>
            <span class="min-w-0 flex-1">
                <span class="block text-sm font-semibold text-white">
                    {{ $onboarding['complete'] ? 'Aan de slag: alles staat klaar om te beginnen' : 'Aan de slag: '.$onboarding['open'].' '.($onboarding['open'] === 1 ? 'stap' : 'stappen').' nog te doen' }}
                </span>
                <span class="block text-xs text-brand-300/70">Lees hoe het systeem werkt en loop de checklist door. {{ $onboarding['done'] }} van {{ $onboarding['total'] }} stappen zijn klaar.</span>
            </span>
            <x-icon name="chevron-right" class="h-5 w-5 shrink-0 text-brand-400 transition group-hover:translate-x-0.5" />
        </a>
    @endif

    @include('dashboard.partials.vandaag-kop')

    <div class="grid gap-x-4 gap-y-6 lg:grid-cols-3">

        {{-- Werklijst centraal: actie gaat vóór informatie --}}
        <section class="lg:col-span-2">
            @php
                // Eén rij-opmaak voor alle acties: getint icoon, titel, subregel en een knop rechts.
                $iconClasses = fn (bool $urgent) => $urgent ? 'bg-red-500/15 text-red-400' : 'bg-brand-500/15 text-brand-400';
                $pillClasses = 'shrink-0 rounded-full border border-surface-600 px-4 py-1.5 text-sm font-semibold text-white transition hover:border-surface-500 hover:bg-surface-750';
            @endphp

            <div class="overflow-hidden rounded-2xl border border-surface-700 bg-surface-850">
                <div class="flex items-center justify-between gap-3 border-b border-surface-700 px-5 py-4 lg:px-6">
                    <h2 class="text-base font-bold text-white">Nu <span class="text-brand-500">doen</span></h2>
                    @if ($attentionCount > 0)
                        <a href="{{ route('attention.index') }}" class="text-xs font-semibold text-white transition hover:text-brand-400">Alle {{ $attentionCount }} in inbox</a>
                    @endif
                </div>

                @if ($aandachtTotaal === 0)
                    <div class="p-8 text-center">
                        <span class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-project-500/15 text-project-400"><x-icon name="check" class="h-5 w-5" /></span>
                        <p class="text-sm font-semibold text-white">Niets dat nu aandacht vraagt</p>
                        <p class="mt-0.5 text-xs text-slate-500">Nieuwe signalen verschijnen hier vanzelf.</p>
                    </div>
                @else
                    <div class="divide-y divide-surface-700 px-5 lg:px-6">
                        @foreach ($betaalRisicos as $project)
                            <div class="flex items-center gap-4 py-3.5">
                                <a href="{{ route('projects.show', $project) }}" class="flex min-w-0 flex-1 items-center gap-4">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $iconClasses(true) }}"><x-icon name="currency-euro" class="h-4 w-4" /></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-bold text-white">{{ $project->customer->name }}: aanbetaling nog niet ontvangen</span>
                                        <span class="block truncate text-xs text-slate-400">€ {{ number_format((float) $project->deposit_amount, 0, ',', '.') }} · start {{ $project->start_date->translatedFormat('j M') }}</span>
                                    </span>
                                </a>
                                <a href="{{ route('projects.show', $project) }}" class="{{ $pillClasses }}">Controleer</a>
                            </div>
                        @endforeach

                        @foreach ($opvolgOffertes as $quote)
                            <div class="flex items-center gap-4 py-3.5">
                                <a href="{{ route('quotes.show', $quote) }}" class="flex min-w-0 flex-1 items-center gap-4">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $iconClasses($quote->daysOpen() >= 5) }}"><x-icon name="document-text" class="h-4 w-4" /></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-bold text-white">Offerte {{ $quote->number }} wacht op opvolging</span>
                                        <span class="block truncate text-xs text-slate-400">
                                            {{ $quote->customer->name }} · al {{ $quote->daysOpen() }} {{ $quote->daysOpen() === 1 ? 'dag' : 'dagen' }} open · € {{ number_format((float) $quote->total, 0, ',', '.') }}@if ($quote->viewed_count > 0) · {{ $quote->viewed_count }}× bekeken @endif
                                        </span>
                                    </span>
                                </a>
                                <a href="{{ route('quotes.show', $quote) }}" class="{{ $pillClasses }}">Bekijk</a>
                            </div>
                        @endforeach

                        @foreach ($opvolgLeads as $lead)
                            @php $teLaat = $lead->next_action_at?->isPast() ?? false; @endphp
                            <div class="flex items-center gap-4 py-3.5">
                                <a href="{{ route('leads.show', $lead) }}" class="flex min-w-0 flex-1 items-center gap-4">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $iconClasses($teLaat) }}"><x-icon name="phone" class="h-4 w-4" /></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-bold text-white">
                                            {{ ucfirst($lead->next_action ?? ($lead->status === \App\Enums\LeadStatus::Nieuw ? 'nieuwe aanvraag opvolgen' : 'opvolgen')) }}: {{ $lead->customer->name }}@if ($teLaat) (te laat)@endif
                                        </span>
                                        <span class="block truncate text-xs text-slate-400">
                                            @if ($lead->next_action_at){{ $lead->next_action_at->isToday() ? $lead->next_action_at->format('H:i') : $lead->next_action_at->translatedFormat('j M H:i') }} · @endif{{ $lead->service ?? 'Aanvraag' }} · {{ $lead->status->label() }}
                                        </span>
                                    </span>
                                </a>
                                @if ($lead->customer->phone)
                                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $lead->customer->phone) }}" class="{{ $pillClasses }}">Bellen</a>
                                @else
                                    <a href="{{ route('leads.show', $lead) }}" class="{{ $pillClasses }}">Bekijk</a>
                                @endif
                            </div>
                        @endforeach

                        @foreach ($taken as $task)
                            <div class="flex items-center gap-4 py-3.5">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $iconClasses($task->isOverdue()) }}"><x-icon name="clipboard-check" class="h-4 w-4" /></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-bold text-white">{{ $task->title }}@if ($task->isOverdue()) (te laat)@endif</span>
                                    <span class="block truncate text-xs text-slate-400">
                                        {{ $task->customer?->name ?? $task->project?->name ?? 'Algemeen' }}@if ($task->deadline) · {{ $task->deadline->isToday() ? 'vandaag' : $task->deadline->translatedFormat('j M') }}@endif · prioriteit {{ strtolower($task->priority->label()) }}
                                    </span>
                                </span>
                                <button type="button"
                                        x-data
                                        @click="patchJson('{{ route('tasks.status', $task) }}', { status: 'afgerond' }).then(() => window.location.reload())"
                                        class="{{ $pillClasses }}">Afronden</button>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        {{-- Rechterkolom: planning als tijdlijn + snel starten --}}
        <div class="space-y-6">
            <section class="overflow-hidden rounded-2xl border border-surface-700 bg-surface-850">
                <div class="flex items-center justify-between gap-3 border-b border-surface-700 px-5 py-4">
                    <h2 class="text-base font-bold text-white">Vandaag <span class="text-brand-500">gepland</span></h2>
                    <a href="{{ route('planning.index') }}" class="text-xs font-semibold text-white transition hover:text-brand-400">Naar planning</a>
                </div>

                @if ($planningVandaag->isEmpty())
                    <div class="p-6 text-center">
                        <span class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-planning-500/15 text-planning-400"><x-icon name="calendar" class="h-5 w-5" /></span>
                        <p class="text-sm font-semibold text-white">Niets gepland voor vandaag</p>
                    </div>
                @else
                    <div class="divide-y divide-surface-700 px-5">
                        @foreach ($planningVandaag as $entry)
                            @php
                                // Subregel: waar het over gaat, wat voor afspraak, en wie het doet.
                                $context = $entry->title ? ($entry->project?->name ?? $entry->customer?->name) : null;
                                $subregel = collect([$context, $entry->type->label(), $entry->user?->name])->filter()->implode(' · ');
                                $dossier = $entry->project ? route('projects.show', $entry->project) : ($entry->lead ? route('leads.show', $entry->lead) : null);
                            @endphp
                            <div class="flex items-center gap-4 py-3.5">
                                <span class="w-12 shrink-0 text-sm font-bold text-white">{{ $entry->start_time ? substr($entry->start_time, 0, 5) : 'Dag' }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-bold text-white">{{ $entry->displayTitle() }}</span>
                                    <span class="block truncate text-xs text-slate-400">{{ $subregel }}</span>
                                </span>
                                @if ($dossier)
                                    <a href="{{ $dossier }}" class="shrink-0 rounded-full border border-surface-600 px-3 py-1 text-xs font-semibold text-white transition hover:border-surface-500 hover:bg-surface-750">Dossier</a>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="overflow-hidden rounded-2xl border border-surface-700 bg-surface-850">
                <div class="flex items-center justify-between gap-3 border-b border-surface-700 px-5 py-4">
                    <h2 class="text-base font-bold text-white">Mijn <span class="text-brand-500">taken</span></h2>
                    <a href="{{ route('tasks.index') }}" class="text-xs font-semibold text-white transition hover:text-brand-400">Alle taken</a>
                </div>

                @if ($mijnTaken->isEmpty())
                    <div class="p-6 text-center">
                        <span class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-project-500/15 text-project-400"><x-icon name="check" class="h-5 w-5" /></span>
                        <p class="text-sm font-semibold text-white">Geen open taken</p>
                    </div>
                @else
                    <div class="divide-y divide-surface-700 px-5">
                        @foreach ($mijnTaken as $task)
                            @php
                                $wanneer = $task->deadline
                                    ? ($task->deadline->isToday() ? 'Vandaag' : ($task->isOverdue() ? 'Te laat, '.$task->deadline->translatedFormat('j M') : $task->deadline->translatedFormat('j M')))
                                    : null;
                                $iconTint = $task->isOverdue()
                                    ? 'bg-red-500/15 text-red-400'
                                    : ($task->deadline?->isToday() ? 'bg-brand-500/15 text-brand-400' : 'bg-surface-750 text-slate-400');
                            @endphp
                            <div class="flex items-center gap-4 py-3.5">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $iconTint }}"><x-icon name="clipboard-check" class="h-4 w-4" /></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-bold text-white">{{ $task->title }}</span>
                                    <span class="block truncate text-xs text-slate-400">{{ collect([$wanneer, $task->project?->name ?? $task->customer?->name])->filter()->implode(' · ') ?: 'Geen deadline' }}</span>
                                </span>
                                <button type="button"
                                        x-data
                                        @click="patchJson('{{ route('tasks.status', $task) }}', { status: 'afgerond' }).then(() => window.location.reload())"
                                        class="flex h-8 w-10 shrink-0 items-center justify-center rounded-full border border-surface-600 text-white transition hover:border-project-500 hover:bg-project-500/10"
                                        title="Afronden" aria-label="Taak {{ $task->title }} afronden">
                                    <x-icon name="check" class="h-4 w-4" />
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

        {{-- Projecten onderaan, vier op een rij --}}
        @if ($recenteProjecten->isNotEmpty())
            <section class="overflow-hidden rounded-2xl border border-surface-700 bg-surface-850 lg:col-span-3">
                <div class="flex items-center justify-between gap-3 border-b border-surface-700 px-5 py-4 lg:px-6">
                    <h2 class="text-base font-bold text-white">In <span class="text-brand-500">uitvoering</span></h2>
                    <a href="{{ route('projects.index') }}" class="text-xs font-semibold text-white transition hover:text-brand-400">Alle projecten</a>
                </div>
                <div data-motion-fan class="grid grid-cols-1 gap-3 p-5 sm:grid-cols-2 lg:p-6 xl:grid-cols-4">
                    @foreach ($recenteProjecten as $project)
                        <x-project-tile :project="$project" :health="$projectHealth[$project->id] ?? null" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>

</x-layouts.app>
