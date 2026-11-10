@props([
    'label',
    'value',
    'sub' => null,
    // De eerste kaart van een rij is navy met een oranje accent: dat is het
    // getal waar het die week om draait.
    'dark' => false,
    'alert' => false,
    'href' => null,
])

@php
    $tag = $href ? 'a' : 'div';
    $classes = $dark
        ? 'border-navy-900 bg-navy-950'
        : 'border-surface-700 bg-surface-800';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => 'motion-lift flex flex-col gap-1 rounded-2xl border px-4 py-4 transition '.$classes.($href ? ' hover:border-surface-600' : '')]) }}>

    <span class="text-[11px] font-semibold tracking-wide {{ $dark ? 'text-slate-400' : 'text-slate-500' }}">{{ $label }}</span>

    <span data-count class="text-2xl leading-none font-semibold tracking-tight text-white tabular-nums {{ $dark ? 'lg:text-3xl' : '' }}">
        {{ $value }}
    </span>

    @if ($sub)
        <span class="flex items-center gap-1.5 text-xs {{ $alert ? 'text-red-400' : ($dark ? 'text-slate-400' : 'text-slate-500') }}">
            {{ $sub }}
        </span>
    @endif
</{{ $tag }}>
