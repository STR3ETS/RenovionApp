@props(['title', 'subtitle' => null, 'image' => null, 'eyebrow' => null, 'category' => null])

@php
    // Eyebrow in de categoriekleur van de module (stijlgids §03).
    $eyebrowColor = match ($category) {
        'lead' => 'text-lead-600',
        'klant' => 'text-klant-600',
        'calc' => 'text-calc-600',
        'offerte' => 'text-offerte-600',
        'project' => 'text-project-600',
        'planning' => 'text-planning-600',
        default => 'text-gray-400',
    };
@endphp

<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div class="flex min-w-0 items-center gap-3">
        @if ($image)
            <img src="{{ $image }}" alt="" class="h-11 w-11 shrink-0 rounded-xl object-cover ring-1 ring-gray-200">
        @endif
        <div class="min-w-0">
            @if ($eyebrow)
                <p class="text-[11px] font-bold tracking-widest uppercase {{ $eyebrowColor }}">{{ $eyebrow }}</p>
            @endif
            <h1 class="truncate text-xl font-bold text-navy-950 lg:text-2xl">{{ $title }}</h1>
            @if ($subtitle)
                <p class="mt-0.5 truncate text-sm text-gray-500">{{ $subtitle }}</p>
            @endif
        </div>
    </div>
    @if (trim($slot))
        <div class="flex flex-wrap items-center gap-2">
            {{ $slot }}
        </div>
    @endif
</div>
