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

    // Op de navy kaart is het valutateken oranje, zoals in het prototype.
    $waarde = e(trim((string) $value));

    if ($dark) {
        $waarde = preg_replace('/^(?:EUR|€)\s*(.*)$/u', '<b>€</b> <span data-count>$1</span>', $waarde, 1, $geld) ?? $waarde;
    } else {
        $waarde = preg_replace('/^EUR\s*/u', '€ ', $waarde) ?? $waarde;
    }
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => 'kpi motion-lift'.($dark ? ' dark on-navy' : '')]) }}>

    <span class="lbl">{{ $label }}</span>

    <span @if (empty($geld)) data-count @endif class="v">{!! $waarde !!}</span>

    @if ($sub)
        <span class="s {{ $alert ? 'alert' : '' }}">{{ $sub }}</span>
    @endif
</{{ $tag }}>
