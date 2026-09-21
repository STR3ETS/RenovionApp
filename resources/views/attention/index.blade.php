<x-layouts.app title="Aandacht">

    <x-page-header title="Aandacht" subtitle="Alleen uitzonderingen — wat goed loopt zie je hier niet" />

    @if ($items->isEmpty())
        <x-empty-state title="Niets vraagt op dit moment aandacht"
                       subtitle="Alle projecten, offertes en taken lopen op schema." />
    @else
        <div class="space-y-2">
            @foreach ($items as $item)
                <a href="{{ $item['url'] }}"
                   class="flex items-center gap-3 rounded-xl border bg-white p-3 transition hover:border-brand-400 {{ $item['severity'] === 'rood' ? 'border-red-200' : 'border-amber-200' }}">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $item['severity'] === 'rood' ? 'bg-red-100 text-red-600' : 'bg-amber-100 text-amber-600' }}">
                        <x-icon :name="$item['icon']" class="h-4.5 w-4.5" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-navy-900">{{ $item['title'] }}</span>
                        @if ($item['subtitle'])
                            <span class="block truncate text-xs text-gray-500">{{ $item['subtitle'] }}</span>
                        @endif
                    </span>
                    <span class="hidden rounded-full px-2.5 py-0.5 text-[11px] font-semibold whitespace-nowrap sm:block {{ $item['severity'] === 'rood' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' }}">
                        {{ $item['label'] }}
                    </span>
                    <x-signal-dot :color="$item['severity'] === 'rood' ? 'red' : 'amber'" class="sm:hidden" />
                </a>
            @endforeach
        </div>
    @endif

</x-layouts.app>
