@php
    // Boven honderd procent is geen prestatie maar een probleem: dan staat er
    // meer ingepland dan iemand in zijn uren kan doen.
    $tone = fn (int $pct) => match (true) {
        $pct > 100 => ['bg-red-500', 'text-red-400'],
        $pct >= 80 => ['bg-green-500', 'text-green-400'],
        $pct > 0 => ['bg-brand-500', 'text-brand-400'],
        default => ['bg-surface-600', 'text-slate-500'],
    };
@endphp

<x-layouts.app title="Capaciteit">
    <x-page-header title="Capaciteit" eyebrow="Uitvoering" category="planning"
                   subtitle="Bezetting per week. Boven honderd procent staat er meer ingepland dan iemand kan doen.">
        @include('planning.partials.view-switch', ['view' => $view])
    </x-page-header>

    <div class="mb-4 flex items-center gap-2">
        <a href="{{ route('planning.index', ['weergave' => 'capaciteit', 'week' => $start->copy()->subWeeks(4)->toDateString()]) }}"
           class="flex h-9 w-9 items-center justify-center rounded-full border border-surface-700 text-slate-400 transition hover:text-white">
            <x-icon name="chevron-left" class="h-4 w-4" />
        </a>
        <span class="text-sm font-semibold text-white">
            Week {{ $start->isoWeek() }} tot {{ $start->copy()->addWeeks(3)->isoWeek() }}
        </span>
        <a href="{{ route('planning.index', ['weergave' => 'capaciteit', 'week' => $start->copy()->addWeeks(4)->toDateString()]) }}"
           class="flex h-9 w-9 items-center justify-center rounded-full border border-surface-700 text-slate-400 transition hover:text-white">
            <x-icon name="chevron-right" class="h-4 w-4" />
        </a>
    </div>

    @if ($rows->isEmpty())
        <x-empty-state icon="identification" category="planning" title="Nog geen planbare mensen"
                       subtitle="Zet bij Mensen en partners de uren per week en vink Planbaar aan." />
    @else
        <div class="overflow-x-auto rounded-2xl border border-surface-700 bg-surface-800">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-surface-700 bg-surface-850 text-[10px] font-bold tracking-wider text-slate-500 uppercase">
                        <th class="px-4 py-3 text-left">Medewerker</th>
                        @foreach ($weeks as $week)
                            <th class="px-4 py-3 text-center">
                                Week {{ $week->isoWeek() }}
                                <span class="mt-0.5 block font-normal normal-case">{{ $week->translatedFormat('j M') }}</span>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-b border-surface-700 last:border-b-0">
                            <td class="px-4 py-3">
                                <p class="truncate text-sm font-semibold text-white">{{ $row['person']->name }}</p>
                                <p class="truncate text-xs text-slate-500">
                                    {{ $row['person']->job_title ?: $row['person']->role->label() }},
                                    {{ $row['person']->weekly_capacity }} uur per week
                                </p>
                            </td>

                            @foreach ($row['weeks'] as $week)
                                @php([$bar, $text] = $tone($week['percentage']))
                                <td class="px-4 py-3">
                                    <p class="text-center text-sm font-bold {{ $text }}">{{ $week['percentage'] }}%</p>
                                    <span class="mx-auto mt-1 block h-1.5 w-full max-w-24 overflow-hidden rounded-full bg-surface-700">
                                        <span class="block h-full rounded-full {{ $bar }}" style="width: {{ min(100, $week['percentage']) }}%"></span>
                                    </span>
                                    <p class="mt-1 text-center text-[11px] text-slate-500">
                                        {{ $week['planned'] }} van {{ $week['available'] }} uur
                                        @if ($week['absent'] > 0)
                                            <span class="block text-amber-400">{{ $week['absent'] }} dagen weg</span>
                                        @endif
                                    </p>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex flex-wrap gap-4 text-xs text-slate-400">
            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-brand-500"></span> Ruimte over</span>
            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-green-500"></span> Goed bezet</span>
            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-red-500"></span> Overvol, iets moet eruit</span>
        </div>
    @endif
</x-layouts.app>
