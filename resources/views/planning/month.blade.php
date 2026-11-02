@php
    // De kleur van een balk volgt het soort afspraak, zodat je projectwerk van
    // een opname kunt onderscheiden zonder te lezen.
    $barClass = fn ($entry) => match ($entry->type->value) {
        'project' => 'bg-project-500/70',
        'intake' => 'bg-lead-500/70',
        'afspraak' => 'bg-calc-500/70',
        'terugbel' => 'bg-planning-500/70',
        default => 'bg-surface-600',
    };
@endphp

<x-layouts.app title="Planning per maand">
    <x-page-header title="Planning" eyebrow="Uitvoering" category="planning"
                   :subtitle="$month->translatedFormat('F Y').', '.$days.' dagen'">
        @include('planning.partials.view-switch', ['view' => $view])
    </x-page-header>

    <div class="mb-4 flex items-center gap-2">
        <a href="{{ route('planning.index', ['weergave' => 'maand', 'maand' => $month->copy()->subMonth()->format('Y-m')]) }}"
           class="flex h-9 w-9 items-center justify-center rounded-full border border-surface-700 text-slate-400 transition hover:text-white">
            <x-icon name="chevron-left" class="h-4 w-4" />
        </a>
        <span class="text-sm font-semibold text-white">{{ $month->translatedFormat('F Y') }}</span>
        <a href="{{ route('planning.index', ['weergave' => 'maand', 'maand' => $month->copy()->addMonth()->format('Y-m')]) }}"
           class="flex h-9 w-9 items-center justify-center rounded-full border border-surface-700 text-slate-400 transition hover:text-white">
            <x-icon name="chevron-right" class="h-4 w-4" />
        </a>
    </div>

    @if ($rows->isEmpty())
        <x-empty-state icon="calendar" category="planning" title="Niets ingepland deze maand"
                       subtitle="Plan werk in vanuit de weekweergave of vanuit een project." />
    @else
        <div class="overflow-hidden rounded-2xl border border-surface-700 bg-surface-800">
            {{-- De dagen als kop --}}
            <div class="flex border-b border-surface-700 bg-surface-850">
                <div class="w-36 shrink-0 border-r border-surface-700 px-3 py-2 text-[10px] font-bold tracking-wider text-slate-500 uppercase">
                    Medewerker
                </div>
                <div class="flex min-w-0 flex-1">
                    @foreach (range(1, $days) as $day)
                        @php($date = $month->copy()->day($day))
                        <div class="flex-1 py-2 text-center text-[10px] {{ $date->isWeekend() ? 'bg-surface-900/50 text-slate-600' : 'text-slate-500' }} {{ $date->isToday() ? 'bg-brand-500/10 font-bold text-brand-300' : '' }}">
                            {{ $day }}
                        </div>
                    @endforeach
                </div>
            </div>

            @foreach ($rows as $row)
                <div class="flex border-b border-surface-700 last:border-b-0">
                    <div class="w-36 shrink-0 border-r border-surface-700 px-3 py-3">
                        <p class="truncate text-sm font-semibold text-white">{{ $row['person']->name }}</p>
                        <p class="truncate text-xs text-slate-500">{{ $row['person']->job_title ?: $row['person']->role->label() }}</p>
                    </div>

                    <div class="relative min-w-0 flex-1 py-2">
                        {{-- Weekendstrepen als achtergrond --}}
                        <div class="absolute inset-0 flex">
                            @foreach (range(1, $days) as $day)
                                <div class="flex-1 {{ $month->copy()->day($day)->isWeekend() ? 'bg-surface-900/50' : '' }}"></div>
                            @endforeach
                        </div>

                        <div class="relative space-y-1">
                            @forelse ($row['bars'] as $bar)
                                <a href="{{ $bar['entry']->project ? route('projects.show', $bar['entry']->project) : route('planning.index') }}"
                                   class="relative block h-6 rounded {{ $barClass($bar['entry']) }} transition hover:brightness-125"
                                   style="margin-left: {{ $bar['left'] }}%; width: {{ $bar['width'] }}%"
                                   title="{{ $bar['entry']->displayTitle() }}, {{ $bar['entry']->date->translatedFormat('j M') }}">
                                    <span class="absolute inset-0 flex items-center truncate px-2 text-[11px] font-semibold text-white">
                                        {{ $bar['entry']->displayTitle() }}
                                    </span>
                                </a>
                            @empty
                                <div class="h-6"></div>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.app>
