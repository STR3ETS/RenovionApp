@props(['dark' => false])

{{-- Renovion OS-logo van de iOS/Android-app. Inline, want een <img> kan de lettertypen van de pagina niet gebruiken. --}}
<svg viewBox="70 88 470 156" role="img" aria-label="Renovion OS" {{ $attributes->merge(['class' => 'h-12 w-auto']) }}>
    <path d="M117 205H80V136L140 99L200 136V152" fill="none" stroke="#F26A1B" stroke-width="7" />
    <text x="124" y="205" textLength="288" lengthAdjust="spacingAndGlyphs" font-family="Outfit, 'Plus Jakarta Sans', sans-serif" font-size="70" font-weight="500" fill="{{ $dark ? '#0D1A2E' : '#FFFFFF' }}">Renovion</text>
    <text x="428" y="205" textLength="96" lengthAdjust="spacingAndGlyphs" font-family="Outfit, 'Plus Jakarta Sans', sans-serif" font-size="70" font-weight="700" fill="#F26A1B">OS</text>
    <text x="128" y="236" font-family="'Plus Jakarta Sans', sans-serif" font-size="15" font-weight="500" textLength="396" lengthAdjust="spacing" fill="{{ $dark ? '#5E6A80' : '#A9B5C8' }}">ÉÉN PLATFORM. HELE BOUWPROJECTEN.</text>
</svg>
