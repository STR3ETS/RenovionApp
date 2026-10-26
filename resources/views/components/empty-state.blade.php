@props(['title', 'subtitle' => null, 'icon' => null, 'category' => null])

@php
    // Lege staat met categorie-icoon (stijlgids §06): nooit alleen tekst.
    $chip = match ($category) {
        'lead' => 'bg-lead-100 text-lead-600',
        'klant' => 'bg-klant-100 text-klant-600',
        'calc' => 'bg-calc-100 text-calc-600',
        'offerte' => 'bg-offerte-100 text-offerte-600',
        'project' => 'bg-project-100 text-project-600',
        'planning' => 'bg-planning-100 text-planning-600',
        default => 'bg-gray-100 text-gray-400',
    };
@endphp

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-8 text-center']) }}>
    @if ($icon)
        <span class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full {{ $chip }}">
            <x-icon :name="$icon" class="h-5 w-5" />
        </span>
    @endif
    <p class="text-sm font-semibold text-navy-900">{{ $title }}</p>
    @if ($subtitle)
        <p class="mt-1 text-xs text-gray-400">{{ $subtitle }}</p>
    @endif
    @if (trim($slot))
        <div class="mt-4">{{ $slot }}</div>
    @endif
</div>
