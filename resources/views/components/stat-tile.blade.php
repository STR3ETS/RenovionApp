@props(['label', 'value', 'sub' => null, 'icon' => null, 'href' => null, 'alert' => false, 'category' => null])

@php
    // Statcard (stijlgids §06): zachte categorietint met gevulde chip.
    $styles = match ($category) {
        'lead' => ['card' => 'border-lead-200 bg-lead-50', 'chip' => 'bg-lead-500 text-white', 'label' => 'text-lead-700', 'sub' => 'text-lead-700/60'],
        'klant' => ['card' => 'border-klant-200 bg-klant-50', 'chip' => 'bg-klant-500 text-white', 'label' => 'text-klant-700', 'sub' => 'text-klant-700/60'],
        'calc' => ['card' => 'border-calc-200 bg-calc-50', 'chip' => 'bg-calc-500 text-white', 'label' => 'text-calc-700', 'sub' => 'text-calc-700/60'],
        'offerte' => ['card' => 'border-offerte-200 bg-offerte-50', 'chip' => 'bg-offerte-500 text-white', 'label' => 'text-offerte-700', 'sub' => 'text-offerte-700/60'],
        'project' => ['card' => 'border-project-200 bg-project-50', 'chip' => 'bg-project-500 text-white', 'label' => 'text-project-700', 'sub' => 'text-project-700/60'],
        'planning' => ['card' => 'border-planning-200 bg-planning-50', 'chip' => 'bg-planning-500 text-white', 'label' => 'text-planning-700', 'sub' => 'text-planning-700/60'],
        default => ['card' => 'border-gray-200 bg-white', 'chip' => 'bg-navy-950 text-white', 'label' => 'text-gray-500', 'sub' => 'text-gray-400'],
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
    <p class="mt-1 text-2xl font-bold text-navy-950 lg:text-3xl">{{ $value }}</p>
    @if ($sub)
        <p class="mt-0.5 text-xs font-medium {{ $alert ? 'text-red-600' : $styles['sub'] }}">{{ $sub }}</p>
    @endif
</a>
