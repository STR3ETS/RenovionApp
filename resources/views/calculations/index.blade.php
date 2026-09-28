<x-layouts.app title="Calculaties">

    <x-page-header title="Calculaties" subtitle="{{ $calculations->total() }} calculaties">
        <a href="{{ route('calculations.create') }}" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-600">+ Calculatie</a>
    </x-page-header>

    <div class="space-y-2">
        @forelse ($calculations as $calculation)
            <a href="{{ route('calculations.show', $calculation) }}" class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-3 transition hover:border-brand-400">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-500"><x-icon name="calculator" class="h-4.5 w-4.5" /></span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-semibold text-navy-900">{{ $calculation->title }}</span>
                    <span class="block truncate text-xs text-gray-500">
                        {{ $calculation->customer?->name ?? 'Geen klant gekoppeld' }}
                        · {{ $calculation->lines->count() }} regels
                        · {{ $calculation->created_at->translatedFormat('j M Y') }}
                    </span>
                </span>
                <span class="hidden text-sm font-bold text-navy-900 sm:block">€ {{ number_format($calculation->totalExcl(), 0, ',', '.') }} <span class="text-xs font-medium text-gray-400">excl.</span></span>
                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap {{ $calculation->status->badgeClasses() }}">{{ $calculation->status->label() }}</span>
            </a>
        @empty
            <x-empty-state title="Nog geen calculaties" subtitle="Maak een calculatie op basis van een opname, omschrijving of AI-voorstel.">
                <a href="{{ route('calculations.create') }}" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white">+ Eerste calculatie</a>
            </x-empty-state>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $calculations->links() }}
    </div>

</x-layouts.app>
