{{-- De tabbalk uit het prototype: één pilgroep met een rand eromheen, de
     actieve tab is een oranje pil. Horizontaal scrollbaar op mobiel. --}}
<nav {{ $attributes->merge(['class' => 'scrollbar-none mb-4 flex gap-1 overflow-x-auto rounded-full border border-surface-700 bg-surface-850 p-1 lg:w-fit lg:max-w-full']) }}>
    {{ $slot }}
</nav>
