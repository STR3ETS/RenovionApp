@props(['title', 'subtitle' => null, 'image' => null, 'eyebrow' => null, 'category' => null])

@php
    // Eyebrow in de categoriekleur van de module (stijlgids §03).
    $eyebrowColor = match ($category) {
        'lead' => 'text-lead-400',
        'klant' => 'text-klant-400',
        'calc' => 'text-calc-400',
        'offerte' => 'text-offerte-400',
        'project' => 'text-project-400',
        'planning' => 'text-planning-400',
        default => 'text-slate-500',
    };
@endphp

<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div class="flex min-w-0 items-center gap-3">
        @if ($image)
            <img src="{{ $image }}" alt="" class="h-11 w-11 shrink-0 rounded-xl object-cover ring-1 ring-surface-700">
        @endif
        <div class="min-w-0">
            @if ($eyebrow)
                <p class="text-[11px] font-bold tracking-widest uppercase {{ $eyebrowColor }}">{{ $eyebrow }}</p>
            @endif
            <h1 class="truncate text-xl font-bold text-white lg:text-2xl">{{ $title }}</h1>
            @if ($subtitle)
                <p class="mt-0.5 truncate text-sm text-slate-400">{{ $subtitle }}</p>
            @endif
        </div>
    </div>
    @if (trim($slot))
        <div class="flex flex-wrap items-center gap-2">
            {{ $slot }}
        </div>
    @endif
</div>
