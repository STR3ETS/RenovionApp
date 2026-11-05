<x-layouts.app title="Aandacht">

    @nativeapp
        @php $rood = $items->where('severity', 'rood')->count(); @endphp
        <div x-data="{ filter: 'alles' }">
            <x-page-header title="Inbox" :subtitle="\App\Support\Tally::of($items->count(), 'punt vraagt', 'punten vragen').' jouw actie. Wat goed loopt zie je hier niet.'" />

            @if ($items->isEmpty())
                <x-empty-state title="Niets vraagt op dit moment aandacht"
                               subtitle="Alle projecten, offertes en taken lopen op schema." icon="check" category="project" />
            @else
                <div class="app-chips">
                    <button type="button" @click="filter = 'alles'" :class="filter === 'alles' && 'on'" class="app-chip">Te doen <span>{{ $items->count() }}</span></button>
                    <button type="button" @click="filter = 'rood'" :class="filter === 'rood' && 'on'" class="app-chip">Actie nodig <span>{{ $rood }}</span></button>
                    <button type="button" @click="filter = 'oranje'" :class="filter === 'oranje' && 'on'" class="app-chip">Signaal <span>{{ $items->count() - $rood }}</span></button>
                </div>

                <div class="app-card">
                    <div class="app-card-b">
                        @foreach ($items as $item)
                            @php $urgent = $item['severity'] === 'rood'; @endphp
                            <a href="{{ $item['url'] }}" class="app-li ibx" style="--st: {{ $urgent ? '#f26a1b' : '#3e6ba8' }}" x-show="filter === 'alles' || filter === @js($urgent ? 'rood' : 'oranje')">
                                <span class="app-ic {{ $urgent ? 'bad' : 'warn' }}"><x-icon :name="$item['icon']" class="h-[18px] w-[18px]" /></span>
                                <span class="min-w-0 flex-1">
                                    <b>{{ $item['title'] }}</b>
                                    <small class="line-clamp-2"><span class="src">{{ $item['label'] }}</span>@if ($item['subtitle']) {{ $item['subtitle'] }}@endif</small>
                                </span>
                                <x-icon name="chevron-right" class="mt-2.5 h-4 w-4 text-gray-300" />
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @else
    <x-page-header eyebrow="Nova signaleert" title="Aandacht" subtitle="Alleen uitzonderingen - wat goed loopt zie je hier niet" />

    @if ($items->isEmpty())
        <x-empty-state title="Niets vraagt op dit moment aandacht"
                       subtitle="Alle projecten, offertes en taken lopen op schema." icon="check" category="project" />
    @else
        <div class="space-y-2">
            @foreach ($items as $item)
                <a href="{{ $item['url'] }}"
                   class="flex items-center gap-3 rounded-xl border border-gray-200 border-l-4 bg-white p-3 transition hover:shadow-sm {{ $item['severity'] === 'rood' ? 'border-l-red-500' : 'border-l-amber-400' }}">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $item['severity'] === 'rood' ? 'bg-red-100 text-red-600' : 'bg-amber-100 text-amber-700' }}">
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
    @endnativeapp

</x-layouts.app>
