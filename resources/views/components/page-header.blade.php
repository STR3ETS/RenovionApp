@props(['title', 'subtitle' => null, 'image' => null, 'eyebrow' => null, 'category' => null])

@php
    // Eyebrow in de categoriekleur van de module (stijlgids §03).
    $eyebrowColor = match ($category) {
        'lead' => 'text-lead-700',
        'klant' => 'text-klant-700',
        'calc' => 'text-calc-700',
        'offerte' => 'text-offerte-700',
        'project' => 'text-project-700',
        'planning' => 'text-planning-700',
        default => 'text-gray-600',
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
            {{-- Op mobiel doorlopen op een tweede regel, zodat lange namen volledig leesbaar blijven. --}}
            <h1 class="text-xl font-bold break-words text-navy-950 lg:truncate lg:text-2xl">{{ $title }}</h1>
            @if ($subtitle)
                <p class="mt-0.5 text-sm text-gray-600 lg:truncate">{{ $subtitle }}</p>
            @endif
        </div>
    </div>
    @if (trim($slot))
        <div class="flex flex-wrap items-center gap-2">
            {{ $slot }}
        </div>
    @endif
</div>
