{{-- De pilbalk uit het prototype: één groep met een rand eromheen. De actieve
     tab is navy in het lichte thema en oranje in het donkere. Horizontaal
     scrollbaar op mobiel. --}}
<nav {{ $attributes->merge(['class' => 'tabs mb-4']) }}>
    {{ $slot }}
</nav>
