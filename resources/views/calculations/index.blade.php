<x-layouts.app title="Calculaties">

    <x-page-header eyebrow="Calculeren" category="calc" title="Calculaties" subtitle="{{ \App\Support\Tally::of($calculations->total(), 'calculatie', 'calculaties') }}">
        <a href="{{ route('calculations.create') }}" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-bold text-white transition hover:bg-brand-600 active:bg-brand-600"><x-app-label web="+ Calculatie" app="Nieuwe calculatie" /></a>
    </x-page-header>

    @nativeapp
        {{-- iOS/Android-app (prototype Renovion OS): één kaart, per calculatie kosten, prijs en marge --}}
        @if ($calculations->isEmpty())
            <x-empty-state title="Nog geen calculaties" subtitle="Maak een calculatie op basis van een opname, omschrijving of AI-voorstel." icon="calculator" category="calc" />
        @else
            <div class="app-card">
                @foreach ($calculations as $calculation)
                    <a href="{{ route('calculations.show', $calculation) }}" class="app-rec">
                        <h4>{{ $calculation->title }}</h4>
                        <small>{{ \App\Support\Tally::of($calculation->lines->count(), 'regel', 'regels') }}, {{ $calculation->created_at->translatedFormat('j M Y') }}</small>
                        <span class="app-fields">
                            <span>
                                <span class="app-lbl">Klant</span>
                                <b>{{ $calculation->customer?->name ?? 'Geen klant gekoppeld' }}</b>
                            </span>
                            <span>
                                <span class="app-lbl">Status</span>
                                <x-status-badge :status="$calculation->status" />
                            </span>
                            <span>
                                <span class="app-lbl">Directe kosten</span>
                                <b>€ {{ number_format($calculation->subtotal(), 2, ',', '.') }}</b>
                            </span>
                            <span>
                                <span class="app-lbl">Excl. btw</span>
                                <b class="font-[family-name:var(--font-display)] font-semibold">€ {{ number_format($calculation->totalExcl(), 2, ',', '.') }}</b>
                            </span>
                            <span>
                                <span class="app-lbl">Marge</span>
                                <b>{{ rtrim(rtrim(number_format((float) $calculation->margin_pct, 1, ',', '.'), '0'), ',') }}%</b>
                            </span>
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    @else
    <div class="space-y-2">
        @forelse ($calculations as $calculation)
            <a href="{{ route('calculations.show', $calculation) }}" class="flex items-center gap-3 rounded-xl border border-gray-200 border-l-4 border-l-calc-500 bg-white p-3 transition hover:shadow-sm">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-calc-100 text-calc-700"><x-icon name="calculator" class="h-4.5 w-4.5" /></span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-semibold text-navy-900">{{ $calculation->title }}</span>
                    <span class="block truncate text-xs text-gray-500">
                        {{ $calculation->customer?->name ?? 'Geen klant gekoppeld' }}, {{ \App\Support\Tally::of($calculation->lines->count(), 'regel', 'regels') }}, {{ $calculation->created_at->translatedFormat('j M Y') }}
                    </span>
                </span>
                <span class="hidden text-sm font-bold text-navy-900 sm:block">€ {{ number_format($calculation->totalExcl(), 0, ',', '.') }} <span class="text-xs font-medium text-gray-500">excl.</span></span>
                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap {{ $calculation->status->badgeClasses() }}">{{ $calculation->status->label() }}</span>
            </a>
        @empty
            <x-empty-state title="Nog geen calculaties" subtitle="Maak een calculatie op basis van een opname, omschrijving of AI-voorstel." icon="calculator" category="calc">
                <a href="{{ route('calculations.create') }}" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-bold text-white">+ Eerste calculatie</a>
            </x-empty-state>
        @endforelse
    </div>
    @endnativeapp

    <div class="mt-4">
        {{ $calculations->links() }}
    </div>

</x-layouts.app>
