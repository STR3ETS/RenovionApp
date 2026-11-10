@props(['label', 'value', 'sub' => null, 'icon' => null, 'href' => null, 'alert' => false, 'category' => null])

@php
    // Statcard (stijlgids §06): zachte categorietint met gevulde chip.
    $styles = match ($category) {
        'lead' => ['card' => 'border-lead-500/30 bg-lead-500/10', 'chip' => 'bg-lead-500 text-white', 'label' => 'text-lead-300', 'sub' => 'text-lead-300/60'],
        'klant' => ['card' => 'border-klant-500/30 bg-klant-500/10', 'chip' => 'bg-klant-500 text-white', 'label' => 'text-klant-300', 'sub' => 'text-klant-300/60'],
        'calc' => ['card' => 'border-calc-500/30 bg-calc-500/10', 'chip' => 'bg-calc-500 text-white', 'label' => 'text-calc-300', 'sub' => 'text-calc-300/60'],
        'offerte' => ['card' => 'border-offerte-500/30 bg-offerte-500/10', 'chip' => 'bg-offerte-500 text-white', 'label' => 'text-offerte-300', 'sub' => 'text-offerte-300/60'],
        'project' => ['card' => 'border-project-500/30 bg-project-500/10', 'chip' => 'bg-project-500 text-white', 'label' => 'text-project-300', 'sub' => 'text-project-300/60'],
        'planning' => ['card' => 'border-planning-500/30 bg-planning-500/10', 'chip' => 'bg-planning-500 text-white', 'label' => 'text-planning-300', 'sub' => 'text-planning-300/60'],
        default => ['card' => 'border-surface-700 bg-surface-800', 'chip' => 'bg-navy-950 text-white', 'label' => 'text-slate-400', 'sub' => 'text-slate-500'],
    };
@endphp

<a href="{{ $href ?? '#' }}"
   {{ $attributes->merge(['class' => 'block rounded-2xl border p-4 transition '.$styles['card'].' '.($href ? 'hover:shadow-sm' : 'pointer-events-none')]) }}>
    <div class="flex items-start justify-between gap-2">
        <p class="text-xs font-semibold {{ $styles['label'] }}">{{ $label }}</p>
        @if ($icon)
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $styles['chip'] }}">
                <x-icon :name="$icon" class="h-4 w-4" />
            </span>
        @endif
    </div>
    <p class="mt-1 text-2xl font-bold text-white lg:text-3xl">{{ $value }}</p>
    @if ($sub)
        <p class="mt-0.5 text-xs font-medium {{ $alert ? 'text-red-400' : $styles['sub'] }}">{{ $sub }}</p>
    @endif
</a>
