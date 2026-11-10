@props([
    'title',
    'accent' => null,
    'subtitle' => null,
    'image' => null,
    // Een klein label boven de kop. Op de meeste schermen is dat de sectie,
    // op detailschermen het recordnummer, dus het blijft staan.
    'eyebrow' => null,
    'category' => null,
])

@php
    // Eén woord van de kop is oranje, net als in het prototype ("Project-
    // dossiers", "Aan de slag"). Zeg je het niet zelf, dan pakt hij het laatste
    // woord, maar alleen bij een korte kop: in een hele zin staat dat raar.
    $kop = trim($title);
    $woorden = preg_split('/\s+/', $kop) ?: [];
    $orange = $accent ?? (count($woorden) > 1 && count($woorden) <= 3 ? end($woorden) : null);
    $plain = $orange !== null && str_ends_with($kop, $orange)
        ? rtrim(mb_substr($kop, 0, mb_strlen($kop) - mb_strlen($orange)))
        : $kop;
@endphp

<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div class="flex min-w-0 items-end gap-3">
        @if ($image)
            <img src="{{ $image }}" alt="" class="h-12 w-12 shrink-0 rounded-xl object-cover ring-1 ring-surface-700">
        @endif

        <div class="min-w-0">
            @if ($eyebrow)
                <p class="mb-1 text-[10.5px] font-semibold tracking-[0.1em] text-slate-500 uppercase">{{ $eyebrow }}</p>
            @endif
            <h1 class="text-2xl font-bold tracking-tight text-white lg:text-[28px]">
                {{ $plain }}@if ($orange) <em class="text-brand-500 not-italic">{{ $orange }}</em>@endif
            </h1>
            @if ($subtitle)
                <p class="mt-1 max-w-2xl text-sm text-slate-400">{{ $subtitle }}</p>
            @endif
        </div>
    </div>

    @if (trim($slot))
        <div class="flex flex-wrap items-center gap-2">
            {{ $slot }}
        </div>
    @endif
</div>
