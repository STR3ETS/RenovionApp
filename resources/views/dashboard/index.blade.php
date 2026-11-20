<x-layouts.app title="Vandaag">

    @php $quotesEnabled = config('renovion.modules.quotes'); @endphp

    @include('dashboard.partials.vandaag-kop')
    @include('dashboard.partials.expiry-popup')

    <div class="grid gap-[14px] lg:grid-cols-[minmax(0,1.7fr)_minmax(0,1fr)]">

        {{-- Werklijst centraal: actie gaat vóór informatie --}}
        <section class="card self-start">
            <div class="card-h">
                <h3>Nu <em>doen</em></h3>
                @if ($attentionCount > 0)
                    <a href="{{ route('attention.index') }}" class="btn xs ghost">Alle {{ $attentionCount }} in inbox</a>
                @endif
            </div>

            @if ($aandachtTotaal === 0)
                <div class="p-8 text-center">
                    <span class="ic ok mx-auto mb-2"><x-icon name="check" /></span>
                    <p class="text-sm font-semibold text-ink">Niets dat nu aandacht vraagt</p>
                    <p class="mt-0.5 text-xs text-muted">Nieuwe signalen verschijnen hier vanzelf.</p>
                </div>
            @else
                <div class="card-b" style="padding-block: 4px">
                    @foreach ($betaalRisicos as $project)
                        <div class="li">
                            <a href="{{ route('projects.show', $project) }}" class="flex min-w-0 flex-1 items-center gap-[14px]">
                                <span class="ic bad"><x-icon name="currency-euro" /></span>
                                <span class="tx">
                                    <b class="truncate">{{ $project->customer->name }}: aanbetaling nog niet ontvangen</b>
                                    <small class="truncate">€ {{ number_format((float) $project->deposit_amount, 0, ',', '.') }}, start {{ $project->start_date->translatedFormat('j M') }}</small>
                                </span>
                            </a>
                            <a href="{{ route('projects.show', $project) }}" class="btn sm">Controleer</a>
                        </div>
                    @endforeach

                    @foreach ($opvolgOffertes as $quote)
                        <div class="li">
                            <a href="{{ route('quotes.show', $quote) }}" class="flex min-w-0 flex-1 items-center gap-[14px]">
                                <span class="ic {{ $quote->daysOpen() >= 5 ? 'bad' : 'or' }}"><x-icon name="document-text" /></span>
                                <span class="tx">
                                    <b class="truncate">Offerte {{ $quote->number }} wacht op opvolging</b>
                                    <small class="truncate">
                                        {{ $quote->customer->name }}, al {{ $quote->daysOpen() }} {{ $quote->daysOpen() === 1 ? 'dag' : 'dagen' }} open, € {{ number_format((float) $quote->total, 0, ',', '.') }}@if ($quote->viewed_count > 0), {{ $quote->viewed_count }}× bekeken @endif
                                    </small>
                                </span>
                            </a>
                            <a href="{{ route('quotes.show', $quote) }}" class="btn sm">Bekijk</a>
                        </div>
                    @endforeach

                    @foreach ($opvolgLeads as $lead)
                        @php $teLaat = $lead->next_action_at?->isPast() ?? false; @endphp
                        <div class="li">
                            <a href="{{ route('leads.show', $lead) }}" class="flex min-w-0 flex-1 items-center gap-[14px]">
                                <span class="ic {{ $teLaat ? 'bad' : 'or' }}"><x-icon name="phone" /></span>
                                <span class="tx">
                                    <b class="truncate">
                                        {{ ucfirst($lead->next_action ?? ($lead->status === \App\Enums\LeadStatus::Nieuw ? 'nieuwe aanvraag opvolgen' : 'opvolgen')) }}: {{ $lead->customer->name }}@if ($teLaat) (te laat)@endif
                                    </b>
                                    <small class="truncate">
                                        @if ($lead->next_action_at){{ $lead->next_action_at->isToday() ? $lead->next_action_at->format('H:i') : $lead->next_action_at->translatedFormat('j M H:i') }}, @endif{{ $lead->service ?? 'Aanvraag' }}, {{ $lead->status->label() }}
                                    </small>
                                </span>
                            </a>
                            @if ($lead->customer->phone)
                                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $lead->customer->phone) }}" class="btn sm">Bellen</a>
                            @else
                                <a href="{{ route('leads.show', $lead) }}" class="btn sm">Bekijk</a>
                            @endif
                        </div>
                    @endforeach

                    @foreach ($taken as $task)
                        <div class="li">
                            <span class="ic {{ $task->isOverdue() ? 'bad' : 'or' }}"><x-icon name="clipboard-check" /></span>
                            <span class="tx">
                                <b class="truncate">{{ $task->title }}@if ($task->isOverdue()) (te laat)@endif</b>
                                <small class="truncate">
                                    {{ $task->customer?->name ?? $task->project?->name ?? 'Algemeen' }}@if ($task->deadline), {{ $task->deadline->isToday() ? 'vandaag' : $task->deadline->translatedFormat('j M') }}@endif, prioriteit {{ strtolower($task->priority->label()) }}
                                </small>
                            </span>
                            <button type="button" x-data
                                    @click="patchJson('{{ route('tasks.status', $task) }}', { status: 'afgerond' }).then(() => window.location.reload())"
                                    class="btn sm">Afronden</button>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Rechterkolom: de dag als tijdlijn, daaronder de eigen taken --}}
        <div class="grid content-start gap-4">
            <section class="card">
                <div class="card-h">
                    <h3>Jouw <em>dag</em></h3>
                    <a href="{{ route('planning.index') }}" class="btn xs ghost">Naar planning</a>
                </div>

                @if ($planningVandaag->isEmpty())
                    <div class="p-6 text-center">
                        <span class="ic mx-auto mb-2"><x-icon name="calendar" /></span>
                        <p class="text-sm font-semibold text-ink">Niets gepland voor vandaag</p>
                    </div>
                @else
                    <div class="card-b" style="padding-block: 4px">
                        @foreach ($planningVandaag as $entry)
                            @php
                                // Subregel: waar het over gaat, wat voor afspraak, en wie het doet.
                                $context = $entry->title ? ($entry->project?->name ?? $entry->customer?->name) : null;
                                $subregel = collect([$context, $entry->type->label(), $entry->user?->name])->filter()->implode(', ');
                                $dossier = $entry->project ? route('projects.show', $entry->project) : ($entry->lead ? route('leads.show', $entry->lead) : null);
                            @endphp
                            <div class="li">
                                <b class="w-12 shrink-0 tabular-nums">{{ $entry->start_time ? substr($entry->start_time, 0, 5) : 'Dag' }}</b>
                                <span class="tx">
                                    <b class="truncate">{{ $entry->displayTitle() }}</b>
                                    <small class="truncate">{{ $subregel }}</small>
                                </span>
                                @if ($dossier)
                                    <a href="{{ $dossier }}" class="btn xs">Dossier</a>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="card">
                <div class="card-h">
                    <h3>Mijn <em>taken</em></h3>
                    <a href="{{ route('tasks.index') }}" class="btn xs ghost">Alle taken</a>
                </div>

                @if ($mijnTaken->isEmpty())
                    <div class="p-6 text-center">
                        <span class="ic ok mx-auto mb-2"><x-icon name="check" /></span>
                        <p class="text-sm font-semibold text-ink">Geen open taken</p>
                    </div>
                @else
                    <div class="card-b" style="padding-block: 4px">
                        @foreach ($mijnTaken as $task)
                            @php
                                $wanneer = $task->deadline
                                    ? ($task->deadline->isToday() ? 'Vandaag' : ($task->isOverdue() ? 'Te laat, '.$task->deadline->translatedFormat('j M') : $task->deadline->translatedFormat('j M')))
                                    : null;
                                $tint = $task->isOverdue() ? 'bad' : ($task->deadline?->isToday() ? 'or' : '');
                            @endphp
                            <div class="li">
                                <span class="ic {{ $tint }}"><x-icon name="clipboard-check" /></span>
                                <span class="tx">
                                    <b class="truncate">{{ $task->title }}</b>
                                    <small class="truncate">{{ collect([$wanneer, $task->project?->name ?? $task->customer?->name])->filter()->implode(', ') ?: 'Geen deadline' }}</small>
                                </span>
                                <button type="button" x-data
                                        @click="patchJson('{{ route('tasks.status', $task) }}', { status: 'afgerond' }).then(() => window.location.reload())"
                                        class="btn xs" title="Afronden" aria-label="Taak {{ $task->title }} afronden">
                                    <x-icon name="check" />
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>

    {{-- Projecten onderaan, als kaarten met stoplichten --}}
    @if ($recenteProjecten->isNotEmpty())
        <section class="card mt-[22px]">
            <div class="card-h">
                <h3>In <em>uitvoering</em></h3>
                <a href="{{ route('projects.index') }}" class="btn xs ghost">Alle projecten</a>
            </div>
            <div data-motion-fan class="card-b uv-grid">
                @foreach ($recenteProjecten as $project)
                    <x-project-tile :project="$project" :health="$projectHealth[$project->id] ?? null" />
                @endforeach
            </div>
        </section>
    @endif

</x-layouts.app>
