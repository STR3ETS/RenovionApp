@props([
    'href',
    'active' => false,
    // Een teller naast het label. Null laat hem weg, 0 blijft staan zodat je
    // ziet dat een filter bestaat maar leeg is.
    'count' => null,
])

<a href="{{ $href }}" @if ($active) aria-current="page" @endif
   {{ $attributes->merge(['class' => 'tab'.($active ? ' on' : '')]) }}>
    {{ $slot }}
    @if ($count !== null)
        <span class="c">{{ $count }}</span>
    @endif
</a>
