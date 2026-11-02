<x-layouts.app title="Aandacht">

    @php
        $rood = $items->where('severity', 'rood')->count();
        $oranje = $items->count() - $rood;

        // Knoptekst per soort signaal, zodat de actie meteen duidelijk is.
        $knop = fn (array $item) => match ($item['icon']) {
            'phone' => 'Opvolgen',
            'document-text' => 'Opvolgen',
            'currency-euro' => 'Controleer',
            'calendar', 'exclamation-triangle' => 'Oplossen',
            default => 'Bekijk',
        };
    @endphp

    <div x-data="{ tab: 'alles' }">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div class="max-w-2xl">
                <p class="flex items-center gap-2 text-[11px] font-bold tracking-widest text-red-400 uppercase">
                    <span class="flex h-6 w-6 items-center justify-center rounded-md bg-red-500/15"><x-icon name="exclamation-triangle" class="h-3.5 w-3.5" /></span>
                    Nova signaleert
                </p>
                <h1 class="mt-3 text-3xl font-bold text-red-400">Aandacht</h1>
                <p class="mt-2 text-sm text-slate-300">
                    @if ($items->isEmpty())
                        Niets vraagt op dit moment aandacht.
                    @else
                        <span class="font-bold text-white">{{ $rood }} {{ $rood === 1 ? 'punt vraagt' : 'punten vragen' }} direct actie</span>, {{ $oranje }} om in de gaten te houden.
                    @endif
                    Alleen uitzonderingen: wat goed loopt zie je hier niet.
                </p>
            </div>
        </div>

        @if ($items->isEmpty())
            <x-empty-state title="Niets vraagt op dit moment aandacht"
                           subtitle="Alle projecten, offertes en taken lopen op schema." icon="check" category="project" />
        @else
            <div class="mb-6 flex flex-wrap gap-2">
                @foreach ([['alles', 'Alles', $items->count()], ['rood', 'Actie nodig', $rood], ['oranje', 'In de gaten houden', $oranje]] as [$key, $label, $count])
                    <button type="button" @click="tab = '{{ $key }}'"
                            :class="tab === '{{ $key }}' ? 'border-red-500 bg-red-500 text-white' : 'border-surface-600 bg-surface-850 text-slate-200 hover:border-surface-500'"
                            class="flex items-center gap-2 rounded-full border px-4 py-1.5 text-sm font-semibold transition">
                        {{ $label }}
                        <span :class="tab === '{{ $key }}' ? 'bg-white/20 text-white' : 'bg-surface-750 text-slate-400'"
                              class="rounded-full px-2 py-0.5 text-[11px] font-bold">{{ $count }}</span>
                    </button>
                @endforeach
            </div>

            <div class="rounded-2xl border border-surface-700 bg-surface-850 p-3">
                @foreach ($items as $item)
                    @php $isRood = $item['severity'] === 'rood'; @endphp
                    <div x-show="tab === 'alles' || tab === '{{ $item['severity'] }}'"
                         class="flex items-center gap-4 border-b border-surface-700 px-2 py-3 last:border-b-0">
                        <span class="h-10 w-1 shrink-0 rounded-full {{ $isRood ? 'bg-red-500' : 'bg-amber-500' }}"></span>
                        <a href="{{ $item['url'] }}" class="flex min-w-0 flex-1 items-center gap-4">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $isRood ? 'bg-red-500/15 text-red-400' : 'bg-amber-500/15 text-amber-400' }}">
                                <x-icon :name="$item['icon']" class="h-4 w-4" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-bold text-white">{{ $item['title'] }}</span>
                                <span class="block truncate text-xs text-slate-400">
                                    <span class="mr-1 text-[11px] font-bold tracking-wider uppercase {{ $isRood ? 'text-red-400' : 'text-amber-400' }}">{{ $item['label'] }}</span>
                                    {{ $item['subtitle'] }}
                                </span>
                            </span>
                        </a>
                        <a href="{{ $item['url'] }}" class="shrink-0 rounded-full border border-surface-600 px-4 py-1.5 text-sm font-semibold text-white transition hover:border-surface-500 hover:bg-surface-750">{{ $knop($item) }}</a>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

</x-layouts.app>
