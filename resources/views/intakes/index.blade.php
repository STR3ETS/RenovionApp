<x-layouts.app title="Intakes en opnames">
    <x-page-header title="Intakes en opnames" category="lead"
                   subtitle="Vijf stappen van aanvraag tot opname. Wat je hier mist, mis je straks in de calculatie." />

    @if ($withoutIntake->isNotEmpty())
        <div class="mb-5 rounded-2xl border border-lead-500/25 bg-lead-500/5 p-5">
            <h2 class="mb-1 text-sm font-bold text-ink">Nog geen <span class="text-brand-500">intake</span></h2>
            <p class="mb-4 text-xs text-muted">
                {{ $withoutIntake->count() }} {{ $withoutIntake->count() === 1 ? 'aanvraag wacht' : 'aanvragen wachten' }} op een intake.
            </p>

            <div class="space-y-2">
                @foreach ($withoutIntake->take(8) as $lead)
                    <div class="flex flex-wrap items-center gap-3 rounded-xl border border-surface-700 bg-surface-800 p-3">
                        <span class="min-w-0 flex-1">
                            <b class="block truncate text-sm font-semibold text-ink">{{ $lead->customer?->name ?? 'Onbekende klant' }}</b>
                            <small class="block truncate text-xs text-muted">
                                {{ $lead->service ?: 'geen dienst opgegeven' }},
                                binnengekomen {{ $lead->created_at->translatedFormat('j M') }}
                            </small>
                        </span>
                        <form method="POST" action="{{ route('intakes.store') }}" class="shrink-0">
                            @csrf
                            <input type="hidden" name="lead_id" value="{{ $lead->id }}">
                            <button type="submit" class="btn pri">
                                Intake starten
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <h2 class="mb-3 text-sm font-bold text-ink">Lopende intakes</h2>

    @if ($open->isEmpty())
        <x-empty-state icon="clipboard-document-list" category="lead" title="Geen lopende intakes"
                       subtitle="Start een intake vanaf een aanvraag, dan loop je de vijf stappen langs." />
    @else
        <div class="space-y-3">
            @foreach ($open as $intake)
                @php($score = $intake->completeness())
                <a href="{{ route('intakes.show', $intake) }}"
                   class="motion-lift block overflow-hidden rounded-2xl border border-surface-700 bg-surface-800 transition hover:border-surface-600">
                    <div class="p-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-lead-500/15 text-lead-400">
                                <x-icon name="clipboard-document-list" class="h-5 w-5" />
                            </span>

                            <span class="min-w-0 flex-1">
                                <b class="block truncate text-sm font-bold text-ink">
                                    {{ $intake->customer?->name ?? $intake->lead?->customer?->name ?? 'Onbekende klant' }}
                                </b>
                                <small class="block truncate text-xs text-muted">
                                    Stap {{ $intake->step + 1 }} van {{ count($steps) }}, {{ $intake->stepLabel() }}
                                    @if ($intake->survey_on)
                                        , opname {{ $intake->survey_on->translatedFormat('j M') }} met {{ $intake->surveyor?->name }}
                                    @endif
                                </small>
                            </span>

                            <span class="shrink-0 text-right">
                                <b class="block text-sm font-bold {{ $score['percentage'] === 100 ? 'text-green-400' : 'text-ink' }}">{{ $score['percentage'] }}%</b>
                                <small class="block text-xs text-faint">compleet</small>
                            </span>
                        </div>

                        {{-- De vijf stappen als balkjes --}}
                        <div class="mt-3 flex gap-1">
                            @foreach ($steps as $index => $step)
                                <span class="h-1 flex-1 rounded-full {{ $index <= $intake->step ? 'bg-lead-500' : 'bg-surface-700' }}"
                                      title="{{ $step[0] }}"></span>
                            @endforeach
                        </div>

                        @if ($score['missing'] !== [])
                            <p class="mt-2 text-xs text-amber-300">
                                Mist nog: {{ collect($score['missing'])->take(3)->join(', ') }}{{ count($score['missing']) > 3 ? ' en meer' : '' }}.
                            </p>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    @if ($done->isNotEmpty())
        <h2 class="mt-6 mb-3 text-sm font-bold text-ink">Afgerond</h2>
        <div class="overflow-hidden rounded-2xl border border-surface-700 bg-surface-800">
            @foreach ($done as $intake)
                <a href="{{ route('intakes.show', $intake) }}"
                   class="flex items-center gap-3 border-b border-surface-700 p-4 transition last:border-b-0 hover:bg-ink/5">
                    <x-icon name="check" class="h-5 w-5 shrink-0 text-green-400" />
                    <span class="min-w-0 flex-1">
                        <b class="block truncate text-sm font-semibold text-ink">
                            {{ $intake->customer?->name ?? $intake->lead?->customer?->name }}
                        </b>
                        <small class="block text-xs text-faint">
                            afgerond {{ $intake->completed_at->translatedFormat('j M Y') }}
                        </small>
                    </span>
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.app>
