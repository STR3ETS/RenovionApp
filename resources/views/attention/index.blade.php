<x-layouts.app title="Aandacht">

    <x-page-header eyebrow="Nova signaleert" title="Aandacht" subtitle="Alleen uitzonderingen - wat goed loopt zie je hier niet" />

    @if ($items->isEmpty())
        <x-empty-state title="Niets vraagt op dit moment aandacht"
                       subtitle="Alle projecten, offertes en taken lopen op schema." icon="check" category="project" />
    @else
        <div class="space-y-2">
            @foreach ($items as $item)
                <a href="{{ $item['url'] }}"
                   class="flex items-center gap-3 rounded-xl border border-surface-700 border-l-4 bg-surface-800 p-3 transition hover:shadow-sm {{ $item['severity'] === 'rood' ? 'border-l-red-500' : 'border-l-amber-400' }}">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $item['severity'] === 'rood' ? 'bg-red-500/15 text-red-400' : 'bg-amber-500/15 text-amber-400' }}">
                        <x-icon :name="$item['icon']" class="h-4.5 w-4.5" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-white">{{ $item['title'] }}</span>
                        @if ($item['subtitle'])
                            <span class="block truncate text-xs text-slate-400">{{ $item['subtitle'] }}</span>
                        @endif
                    </span>
                    <span class="hidden rounded-full px-2.5 py-0.5 text-[11px] font-semibold whitespace-nowrap sm:block {{ $item['severity'] === 'rood' ? 'bg-red-500/15 text-red-300' : 'bg-amber-500/15 text-amber-300' }}">
                        {{ $item['label'] }}
                    </span>
                    <x-signal-dot :color="$item['severity'] === 'rood' ? 'red' : 'amber'" class="sm:hidden" />
                </a>
            @endforeach
        </div>
    @endif

</x-layouts.app>
