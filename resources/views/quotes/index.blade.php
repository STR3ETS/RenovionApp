<x-layouts.app title="Offertes">

    <x-page-header eyebrow="Verkoop" category="offerte" title="Offertes" subtitle="{{ $quotes->total() }} offertes">
        <a href="{{ route('quotes.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-500">+ Offerte</a>
    </x-page-header>

    <div class="mb-4 flex flex-wrap gap-1.5">
        <a href="{{ route('quotes.index') }}"
           class="rounded-full px-3 py-1.5 text-xs font-semibold {{ $status === null ? 'bg-navy-900 text-white' : 'bg-white text-gray-600 border border-gray-300' }}">Alle</a>
        @foreach (\App\Enums\QuoteStatus::cases() as $statusOption)
            <a href="{{ route('quotes.index', ['status' => $statusOption->value]) }}"
               class="rounded-full px-3 py-1.5 text-xs font-semibold {{ $status === $statusOption ? 'bg-navy-900 text-white' : 'bg-white text-gray-600 border border-gray-300' }}">{{ $statusOption->label() }}</a>
        @endforeach
    </div>

    <div class="space-y-2">
        @forelse ($quotes as $quote)
            <a href="{{ route('quotes.show', $quote) }}" class="flex items-center gap-3 rounded-xl border border-gray-200 border-l-4 border-l-offerte-500 bg-white p-3 transition hover:shadow-sm">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-offerte-100 text-offerte-600"><x-icon name="document-text" class="h-4 w-4" /></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold text-navy-900">{{ $quote->customer->name }}</span>
                    <span class="block text-xs text-gray-500">
                        {{ $quote->number }}, € {{ number_format((float) $quote->total, 2, ',', '.') }}
                        @if ($quote->daysOpen() !== null), {{ $quote->daysOpen() }} dagen open @endif
                        @if ($quote->viewed_count > 0), {{ $quote->viewed_count }}× bekeken @endif
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
                <a href="{{ route('quotes.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">+ Eerste offerte</a>
            </x-empty-state>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $quotes->links() }}
    </div>

</x-layouts.app>
