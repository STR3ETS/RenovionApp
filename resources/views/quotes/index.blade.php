<x-layouts.app title="Offertes">

    <x-page-header category="offerte" title="Offertes" subtitle="{{ $quotes->total() }} offertes">
        <a href="{{ route('quotes.create') }}" class="btn pri">+ Offerte</a>
    </x-page-header>

    <div class="tabs mb-4">
        <a href="{{ route('quotes.index') }}"
           class="tab {{ $status === null ? 'on' : '' }}">Alle</a>
        @foreach (\App\Enums\QuoteStatus::cases() as $statusOption)
            <a href="{{ route('quotes.index', ['status' => $statusOption->value]) }}"
               class="tab {{ $status === $statusOption ? 'on' : '' }}">{{ $statusOption->label() }}</a>
        @endforeach
    </div>

    <div class="space-y-2">
        @forelse ($quotes as $quote)
            <a href="{{ route('quotes.show', $quote) }}" class="flex items-center gap-3 rounded-xl border border-surface-700 border-l-4 border-l-offerte-500 bg-surface-800 p-3 transition hover:shadow-sm">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-offerte-500/15 text-offerte-400"><x-icon name="document-text" class="h-4 w-4" /></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold text-ink">{{ $quote->customer->name }}</span>
                    <span class="block text-xs text-muted">
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
                <a href="{{ route('quotes.create') }}" class="btn pri">+ Eerste offerte</a>
            </x-empty-state>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $quotes->links() }}
    </div>

</x-layouts.app>
