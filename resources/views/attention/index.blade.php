@php
    use App\Services\AttentionService;

    // Tellers per soort. "Te doen" is de doorsnede: alles behalve informatie.
    $tellen = fn (string $kind) => $kind === 'todo'
        ? $items->where('kind', '!=', 'info')->count()
        : $items->where('kind', $kind)->count();

    $teDoen = $tellen('todo');
    $terInfo = $tellen('info');

    // Knoptekst per soort signaal, zodat de actie meteen duidelijk is.
    $knop = fn (array $item) => match ($item['kind']) {
        'bevestigen' => 'Controleren',
        'service' => 'Afhandelen',
        'info' => 'Lezen',
        default => match ($item['icon']) {
            'phone', 'document-text' => 'Opvolgen',
            'currency-euro' => 'Controleer',
            'calendar', 'exclamation-triangle' => 'Oplossen',
            default => 'Bekijk',
        },
    };
@endphp

<x-layouts.app title="Inbox">
    <div x-data="{ tab: 'todo' }">
        <div class="mb-6 max-w-2xl">
            <p class="flex items-center gap-2 text-[11px] font-bold tracking-widest text-brand-400 uppercase">
                <span class="flex h-6 w-6 items-center justify-center rounded-md bg-brand-500/15"><x-icon name="exclamation-triangle" class="h-3.5 w-3.5" /></span>
                Nova signaleert
            </p>
            <h1 class="mt-3 text-3xl font-bold text-white">Inbox</h1>
            <p class="mt-2 text-sm text-slate-300">
                @if ($items->isEmpty())
                    Je inbox is leeg. Alles loopt volgens plan.
                @else
                    <span class="font-bold text-white">{{ $teDoen }} {{ $teDoen === 1 ? 'punt vraagt' : 'punten vragen' }} jouw actie</span>@if ($terInfo), {{ $terInfo }} ter informatie @endif.
                    Signalen, werkbonnen, servicemeldingen en berichten op één plek.
                @endif
            </p>
        </div>

        @if ($items->isEmpty())
            <x-empty-state title="Niets meer te doen"
                           subtitle="Alle projecten, offertes, werkbonnen en taken lopen op schema." icon="check" category="project" />
        @else
            <nav class="scrollbar-none mb-6 flex gap-1 overflow-x-auto rounded-full border border-surface-700 bg-surface-850 p-1 lg:w-fit lg:max-w-full">
                @foreach (AttentionService::KINDS as $key => $label)
                    @continue($key !== 'todo' && $tellen($key) === 0)
                    <button type="button" @click="tab = '{{ $key }}'"
                            :class="tab === '{{ $key }}' ? 'bg-brand-500 text-white' : 'text-slate-400 hover:text-white'"
                            class="flex shrink-0 items-center gap-1.5 rounded-full px-3.5 py-2 text-sm font-semibold whitespace-nowrap transition">
                        {{ $label }}
                        <span :class="tab === '{{ $key }}' ? 'bg-white/25 text-white' : 'bg-surface-700 text-slate-400'"
                              class="rounded-full px-1.5 text-[10px] font-bold">{{ $tellen($key) }}</span>
                    </button>
                @endforeach
            </nav>

            <div class="rounded-2xl border border-surface-700 bg-surface-850 p-3">
                @foreach ($items as $item)
                    @php($isRood = $item['severity'] === 'rood')
                    <div x-show="tab === '{{ $item['kind'] }}' || (tab === 'todo' && '{{ $item['kind'] }}' !== 'info')"
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
