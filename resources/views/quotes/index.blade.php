<x-layouts.app title="Offertes">

    <x-page-header eyebrow="Verkoop" category="offerte" title="Offertes" subtitle="{{ \App\Support\Tally::of($quotes->total(), 'offerte', 'offertes') }}">
        <a href="{{ route('quotes.create') }}" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-bold text-white transition hover:bg-brand-600 active:bg-brand-600"><x-app-label web="+ Offerte" app="Nieuwe offerte" /></a>
    </x-page-header>

    <div class="mb-4 flex flex-wrap gap-1.5 app:-mx-4 app:flex-nowrap app:gap-1 app:overflow-x-auto app:px-4 app:pb-1">
        <a href="{{ route('quotes.index') }}"
           class="tap rounded-full px-3 py-1.5 text-xs font-semibold {{ $status === null ? 'bg-navy-900 text-white' : 'bg-white text-gray-600 border border-gray-300' }}">Alle</a>
        @foreach (\App\Enums\QuoteStatus::cases() as $statusOption)
            <a href="{{ route('quotes.index', ['status' => $statusOption->value]) }}"
               class="tap rounded-full px-3 py-1.5 text-xs font-semibold {{ $status === $statusOption ? 'bg-navy-900 text-white' : 'bg-white text-gray-600 border border-gray-300' }}">{{ $statusOption->label() }}</a>
        @endforeach
    </div>

    @nativeapp
        {{-- iOS/Android-app (prototype Renovion OS): één kaart, per offerte nummer, versie en de vier kerngegevens --}}
        @if ($quotes->isEmpty())
            <x-empty-state title="Geen offertes gevonden" icon="document-text" category="offerte" />
        @else
            <div class="app-card">
                @foreach ($quotes as $quote)
                    <div class="app-rec relative">
                        <a href="{{ route('quotes.show', $quote) }}" class="absolute inset-0" aria-label="Offerte {{ $quote->number }} openen"></a>
                        <div class="flex items-start justify-between gap-3">
                            <span class="min-w-0">
                                <h4>{{ $quote->number }}</h4>
                                <small>versie {{ $quote->version }}</small>
                            </span>
                            @if ($quote->daysOpen() !== null && $quote->daysOpen() >= 3)
                                <x-signal-dot :color="$quote->daysOpen() >= 5 ? 'red' : 'amber'" class="mt-2" title="Opvolgen" />
                            @endif
                        </div>
                        <div class="app-fields">
                            <div>
                                <span class="app-lbl">Project</span>
                                <b>{{ $quote->lead?->service ?? $quote->customer->name }}</b>
                                @if ($quote->lead?->service)
                                    <small>{{ $quote->customer->name }}</small>
                                @endif
                            </div>
                            <div>
                                <span class="app-lbl">Verzonden</span>
                                <b>{{ $quote->sent_at?->translatedFormat('j M Y') ?? 'Nog niet' }}</b>
                                @if ($quote->daysOpen() !== null)
                                    <small>{{ \App\Support\Tally::of($quote->daysOpen(), 'dag', 'dagen') }} open{{ $quote->viewed_count > 0 ? ', '.$quote->viewed_count.'× bekeken' : '' }}</small>
                                @endif
                            </div>
                            <div>
                                <span class="app-lbl">Status</span>
                                <x-status-badge :status="$quote->status" />
                            </div>
                            <div>
                                <span class="app-lbl">Aanneemsom excl. btw</span>
                                @if ((float) $quote->subtotal === 0.0 && $quote->status === \App\Enums\QuoteStatus::Concept)
                                    <b class="text-gray-500">Nog leeg</b>
                                @else
                                    <b class="font-[family-name:var(--font-display)] font-semibold">€ {{ number_format((float) $quote->subtotal, 2, ',', '.') }}</b>
                                @endif
                                @if ($quote->status === \App\Enums\QuoteStatus::Concept)
                                    <a href="{{ route('quotes.builder', $quote) }}" class="app-btn relative z-10 mt-2"><x-icon name="pencil" class="h-3.5 w-3.5" /> Builder</a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @else
    <div class="space-y-2">
        @forelse ($quotes as $quote)
            <a href="{{ route('quotes.show', $quote) }}" class="flex items-center gap-3 rounded-xl border border-gray-200 border-l-4 border-l-offerte-500 bg-white p-3 transition hover:shadow-sm">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-offerte-100 text-offerte-700"><x-icon name="document-text" class="h-4 w-4" /></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold text-navy-900">{{ $quote->customer->name }}</span>
                    <span class="block text-xs text-gray-500">
                        {{ collect([
                            $quote->number,
                            '€ '.number_format((float) $quote->total, 2, ',', '.'),
                            $quote->daysOpen() !== null ? $quote->daysOpen().' dagen open' : null,
                            $quote->viewed_count > 0 ? $quote->viewed_count.'× bekeken' : null,
                        ])->filter()->implode(', ') }}
                    </span>
                </span>
                @if ($quote->daysOpen() !== null && $quote->daysOpen() >= 5)
                    <x-signal-dot color="red" title="Al {{ $quote->daysOpen() }} dagen geen reactie" />
                @elseif ($quote->daysOpen() !== null && $quote->daysOpen() >= 3)
                    <x-signal-dot color="amber" title="Opvolgen" />
                @endif
                <x-status-badge :status="$quote->status" />
            </a>
        @empty
            <x-empty-state title="Geen offertes gevonden" icon="document-text" category="offerte">
                <a href="{{ route('quotes.create') }}" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-bold text-white">+ Eerste offerte</a>
            </x-empty-state>
        @endforelse
    </div>
    @endnativeapp

    <div class="mt-4">
        {{ $quotes->links() }}
    </div>

</x-layouts.app>
