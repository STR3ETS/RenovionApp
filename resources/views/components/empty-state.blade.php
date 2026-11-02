@props(['title', 'subtitle' => null, 'icon' => null, 'category' => null])

@php
    // Lege staat met categorie-icoon (stijlgids §06): nooit alleen tekst.
    $chip = match ($category) {
        'lead' => 'bg-lead-500/15 text-lead-400',
        'klant' => 'bg-klant-500/15 text-klant-400',
        'calc' => 'bg-calc-500/15 text-calc-400',
        'offerte' => 'bg-offerte-500/15 text-offerte-400',
        'project' => 'bg-project-500/15 text-project-400',
        'planning' => 'bg-planning-500/15 text-planning-400',
        default => 'bg-surface-750 text-slate-500',
    };
@endphp

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-dashed border-surface-600 bg-surface-800 px-6 py-8 text-center']) }}>
    @if ($icon)
        <span class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full {{ $chip }}">
            <x-icon :name="$icon" class="h-5 w-5" />
        </span>
    @endif
    <p class="text-sm font-semibold text-white">{{ $title }}</p>
    @if ($subtitle)
        <p class="mt-1 text-xs text-slate-500">{{ $subtitle }}</p>
    @endif
    @if (trim($slot))
        <div class="mt-4">{{ $slot }}</div>
    @endif
</div>
