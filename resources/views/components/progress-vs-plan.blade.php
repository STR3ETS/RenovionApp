@props(['health'])

@php
    // Voortgang tegen plan: de balk is de werkelijke voortgang, het streepje
    // waar het project volgens de planning zou moeten staan.
    $variance = $health->scheduleVariance();
    $barColor = match (true) {
        $variance === null => 'bg-brand-500',
        $variance >= 10 => 'bg-red-500',
        $variance >= 5 => 'bg-amber-500',
        default => 'bg-green-500',
    };
@endphp

<div {{ $attributes }}>
    <div class="flex items-end justify-between gap-2">
        <span class="text-xs font-semibold text-slate-400">Voortgang tegen plan</span>
        <span class="text-xs font-bold text-white">{{ $health->progress }}%</span>
    </div>
    <div class="relative mt-1.5 h-2.5 overflow-hidden rounded-full bg-surface-750">
        <div class="h-full rounded-full {{ $barColor }} transition-[width] duration-700 ease-out" style="width: {{ $health->progress }}%"></div>
        @if ($health->expectedProgress !== null)
            <span class="absolute top-0 h-full w-0.5 bg-navy-950/60" style="left: {{ $health->expectedProgress }}%" title="Volgens planning: {{ $health->expectedProgress }}%"></span>
        @endif
    </div>
    <p class="mt-1 text-xs text-slate-500">
        @if ($variance === null)
            Geen start- of opleverdatum ingevuld, dus geen vergelijking met de planning.
        @elseif ($variance > 0)
            {{ $variance }}% achter op planning, zou op {{ $health->expectedProgress }}% moeten staan.
        @elseif ($variance < 0)
            {{ abs($variance) }}% voor op planning.
        @else
            Precies op planning.
        @endif
    </p>
</div>
