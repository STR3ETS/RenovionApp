@props([
    // De slogan onder het woordmerk. Laat hem weg waar het logo klein staat.
    'tagline' => true,
])

{{-- Het woordmerk uit het prototype: huisje in oranje lijn, "Renovion" in wit
     en "OS" in oranje. Alleen voor een donkere (navy) ondergrond. --}}
<svg {{ $attributes }} viewBox="70 88 470 {{ $tagline ? 156 : 126 }}" role="img" aria-label="Renovion OS, een platform voor hele bouwprojecten">
    <path d="M117 205H80V136L140 99L200 136V152" fill="none" stroke="#F26A1B" stroke-width="7" />
    <text x="124" y="205" textLength="288" lengthAdjust="spacingAndGlyphs" font-family="Outfit, 'Plus Jakarta Sans', sans-serif" font-size="70" font-weight="500" fill="#FFFFFF">Renovion</text>
    <text x="428" y="205" textLength="96" lengthAdjust="spacingAndGlyphs" font-family="Outfit, 'Plus Jakarta Sans', sans-serif" font-size="70" font-weight="700" fill="#F26A1B">OS</text>
    @if ($tagline)
        <text x="128" y="236" font-family="'Plus Jakarta Sans', sans-serif" font-size="15" font-weight="500" textLength="396" lengthAdjust="spacing" fill="#A9B5C8">EEN PLATFORM. HELE BOUWPROJECTEN.</text>
    @endif
</svg>
