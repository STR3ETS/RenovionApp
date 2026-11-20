<x-layouts.app title="Calculaties">

    <x-page-header category="calc" title="Calculaties" subtitle="{{ $calculations->total() }} calculaties">
        <a href="{{ route('calculations.create') }}" class="btn pri">+ Calculatie</a>
    </x-page-header>

    <div class="space-y-2">
        @forelse ($calculations as $calculation)
            <a href="{{ route('calculations.show', $calculation) }}" class="flex items-center gap-3 rounded-xl border border-surface-700 border-l-4 border-l-calc-500 bg-surface-800 p-3 transition hover:shadow-sm">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-calc-500/15 text-calc-400"><x-icon name="calculator" class="h-4.5 w-4.5" /></span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-semibold text-ink">{{ $calculation->title }}</span>
                    <span class="block truncate text-xs text-muted">
                        {{ $calculation->customer?->name ?? 'Geen klant gekoppeld' }}
                       , {{ $calculation->lines->count() }} regels
                       , {{ $calculation->created_at->translatedFormat('j M Y') }}
                    </span>
                </span>
                <span class="hidden text-sm font-bold text-ink sm:block">€ {{ number_format($calculation->totalExcl(), 0, ',', '.') }} <span class="text-xs font-medium text-faint">excl.</span></span>
                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap {{ $calculation->status->badgeClasses() }}">{{ $calculation->status->label() }}</span>
            </a>
        @empty
            <x-empty-state title="Nog geen calculaties" subtitle="Maak een calculatie op basis van een opname, omschrijving of AI-voorstel." icon="calculator" category="calc">
                <a href="{{ route('calculations.create') }}" class="btn pri">+ Eerste calculatie</a>
            </x-empty-state>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $calculations->links() }}
    </div>

</x-layouts.app>
