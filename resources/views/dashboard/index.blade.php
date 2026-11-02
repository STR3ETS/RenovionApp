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
            <div class="mb-3 flex items-end justify-between">
                <div>
                    <p class="text-[11px] font-bold tracking-widest text-slate-500 uppercase">Vandaag</p>
                    <h2 class="mt-0.5 text-base font-bold text-white">Nu doen</h2>
                </div>
                @if ($aandachtTotaal > 0)
                    <span class="rounded-full bg-surface-750 px-2.5 py-0.5 text-xs font-semibold text-slate-400">{{ $aandachtTotaal }} {{ $aandachtTotaal === 1 ? 'actie' : 'acties' }}</span>
                @endif
            </div>

            @if ($aandachtTotaal === 0)
                <div class="rounded-2xl border border-dashed border-surface-600 bg-surface-800 p-8 text-center">
                    <span class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-project-500/15 text-project-400"><x-icon name="check" class="h-5 w-5" /></span>
                    <p class="text-sm font-semibold text-white">Niets dat nu aandacht vraagt</p>
                    <p class="mt-0.5 text-xs text-slate-500">Nieuwe signalen verschijnen hier vanzelf.</p>
                </div>
            @else
                <div class="space-y-2">
                    @foreach ($betaalRisicos as $project)
                        <a href="{{ route('projects.show', $project) }}" class="flex items-center gap-3 rounded-xl border border-surface-700 border-l-4 border-l-red-500 bg-surface-800 p-3 transition hover:shadow-sm">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-red-500/15 text-red-400"><x-icon name="currency-euro" class="h-4 w-4" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-white">{{ $project->customer->name }}: aanbetaling nog niet ontvangen</span>
                                <span class="block truncate text-xs text-slate-400">
                                    € {{ number_format((float) $project->deposit_amount, 0, ',', '.') }}
                                   , start {{ $project->start_date->translatedFormat('j M') }}
                                </span>
                            </span>
                            <x-signal-dot color="red" />
                        </a>
                    @endforeach

                    @foreach ($opvolgOffertes as $quote)
                        <a href="{{ route('quotes.show', $quote) }}" class="flex items-center gap-3 rounded-xl border border-surface-700 border-l-4 border-l-offerte-500 bg-surface-800 p-3 transition hover:shadow-sm">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-offerte-500/15 text-offerte-400"><x-icon name="document-text" class="h-4 w-4" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-white">{{ $quote->customer->name }}: offerte {{ $quote->daysOpen() }} dagen open</span>
                                <span class="block truncate text-xs text-slate-400">
                                    € {{ number_format((float) $quote->total, 0, ',', '.') }}
                                    @if ($quote->viewed_count > 0), {{ $quote->viewed_count }}× bekeken @endif
                                </span>
                            </span>
                            <x-signal-dot :color="$quote->daysOpen() >= 5 ? 'red' : 'amber'" />
                        </a>
                    @endforeach

                    @foreach ($opvolgLeads as $lead)
                        <a href="{{ route('leads.show', $lead) }}" class="flex items-center gap-3 rounded-xl border border-surface-700 border-l-4 border-l-lead-500 bg-surface-800 p-3 transition hover:shadow-sm">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-lead-500/15 text-lead-400"><x-icon name="phone" class="h-4 w-4" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-white">
                                    {{ $lead->customer->name }}:
                                    {{ $lead->next_action ?? ($lead->status === \App\Enums\LeadStatus::Nieuw ? 'nieuwe aanvraag opvolgen' : 'opvolgen') }}
                                </span>
                                <span class="block truncate text-xs text-slate-400">
                                    {{ $lead->service ?? 'Aanvraag' }}
                                    @if ($lead->next_action_at), {{ $lead->next_action_at->translatedFormat('j M H:i') }} @endif
                                </span>
                            </span>
                            <x-status-badge :status="$lead->status" />
                        </a>
                    @endforeach

                    @foreach ($taken as $task)
                        <div class="flex items-center gap-3 rounded-xl border border-surface-700 border-l-4 border-l-planning-500 bg-surface-800 p-3">
                            <button
                                x-data
                                @click="patchJson('{{ route('tasks.status', $task) }}', { status: 'afgerond' }).then(() => window.location.reload())"
                                class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2 border-surface-600 transition hover:border-project-500 hover:bg-project-500/10"
                                title="Afronden">
                            </button>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold {{ $task->isOverdue() ? 'text-red-300' : 'text-white' }}">{{ $task->title }}</span>
                                <span class="block truncate text-xs text-slate-400">
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
                <p class="text-[11px] font-bold tracking-widest text-planning-400 uppercase">Planning</p>
                <h2 class="mt-0.5 mb-3 text-base font-bold text-white">Vandaag gepland</h2>
                @if ($planningVandaag->isEmpty())
                    <div class="rounded-2xl border border-dashed border-surface-600 bg-surface-800 p-6 text-center">
                        <span class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-planning-500/15 text-planning-400"><x-icon name="calendar" class="h-5 w-5" /></span>
                        <p class="text-sm font-semibold text-white">Niets gepland voor vandaag</p>
                    </div>
                @else
                    <div class="rounded-2xl border border-surface-700 bg-surface-800 p-4">
                        <div class="relative space-y-4 border-l-2 border-planning-500/30 pl-4">
                            @foreach ($planningVandaag as $entry)
                                <div class="relative">
                                    <span class="absolute top-1.5 -left-[1.4rem] h-2.5 w-2.5 rounded-full border-2 border-white bg-planning-500"></span>
                                    <p class="text-xs font-bold text-planning-300">{{ $entry->start_time ? substr($entry->start_time, 0, 5) : 'Hele dag' }}</p>
                                    <p class="truncate text-sm font-semibold text-white">{{ $entry->displayTitle() }}</p>
                                    <p class="truncate text-xs text-slate-400">{{ $entry->user->name }}, {{ $entry->type->label() }}</p>
                                </div>
                            @endforeach
                        </div>
                        <a href="{{ route('planning.index') }}" class="mt-4 block text-xs font-semibold text-brand-400 transition hover:text-brand-500">Naar de planning →</a>
                    </div>
                @endif
            </section>

            <section>
                <p class="text-[11px] font-bold tracking-widest text-slate-500 uppercase">Snel</p>
                <h2 class="mt-0.5 mb-3 text-base font-bold text-white">Direct starten</h2>
                <div class="grid grid-cols-2 gap-2">
                    <a href="{{ route('leads.create') }}" class="col-span-2 flex items-center justify-center gap-2 rounded-xl bg-brand-500 px-4 py-3 text-sm font-bold text-white transition hover:bg-brand-600">
                        <x-icon name="plus" class="h-4 w-4" /> Nieuwe aanvraag
                    </a>
                    @if ($quotesEnabled)
                        <a href="{{ route('quotes.create') }}" class="flex items-center gap-2 rounded-xl border border-surface-700 bg-surface-800 px-3 py-2.5 text-sm font-semibold text-white transition hover:border-offerte-500 hover:shadow-sm">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-offerte-500/15 text-offerte-400"><x-icon name="document-text" class="h-3.5 w-3.5" /></span> Offerte
                        </a>
                    @endif
                    <a href="{{ route('projects.create') }}" class="flex items-center gap-2 rounded-xl border border-surface-700 bg-surface-800 px-3 py-2.5 text-sm font-semibold text-white transition hover:border-project-500 hover:shadow-sm">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-project-500/15 text-project-400"><x-icon name="building-office" class="h-3.5 w-3.5" /></span> Project
                    </a>
                    <a href="{{ route('planning.index') }}" class="flex items-center gap-2 rounded-xl border border-surface-700 bg-surface-800 px-3 py-2.5 text-sm font-semibold text-white transition hover:border-planning-500 hover:shadow-sm">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-planning-500/15 text-planning-400"><x-icon name="calendar" class="h-3.5 w-3.5" /></span> Planning
                    </a>
                    <a href="{{ route('tasks.index') }}" class="flex items-center gap-2 rounded-xl border border-surface-700 bg-surface-800 px-3 py-2.5 text-sm font-semibold text-white transition hover:border-planning-500 hover:shadow-sm">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-planning-500/15 text-planning-400"><x-icon name="clipboard-check" class="h-3.5 w-3.5" /></span> Taken
                    </a>
                </div>
            </section>
        </div>

        {{-- Projecten onderaan, vier op een rij --}}
        @if ($recenteProjecten->isNotEmpty())
            <section class="lg:col-span-3">
                <div class="mb-3 flex items-end justify-between">
                    <div>
                        <p class="text-[11px] font-bold tracking-widest text-project-400 uppercase">Projecten</p>
                        <h2 class="mt-0.5 text-base font-bold text-white">Recent in uitvoering</h2>
                    </div>
                    <a href="{{ route('projects.index') }}" class="text-xs font-semibold text-brand-400 transition hover:text-brand-500">Alle projecten →</a>
                </div>
                <div data-motion-fan class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($recenteProjecten as $project)
                        <x-project-card :project="$project" :health="$projectHealth[$project->id] ?? null" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>

</x-layouts.app>
