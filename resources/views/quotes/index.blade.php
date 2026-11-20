<x-layouts.app title="Offertes">

    <x-page-header title="Offertes" :subtitle="$quotes->total().' offertes. Een offerte staat in het dossier van het project; een rij opent dat dossier.'">
        <a href="{{ route('quotes.create') }}" class="btn pri"><x-icon name="plus" />Offerte</a>
    </x-page-header>

    <div class="tabs mb-4">
        <a href="{{ route('quotes.index') }}"
           class="tab {{ $status === null ? 'on' : '' }}">Alle</a>
        @foreach (\App\Enums\QuoteStatus::cases() as $statusOption)
            <a href="{{ route('quotes.index', ['status' => $statusOption->value]) }}"
               class="tab {{ $status === $statusOption ? 'on' : '' }}">{{ $statusOption->label() }}</a>
        @endforeach
    </div>

    @if ($quotes->isEmpty())
        <x-empty-state title="Geen offertes gevonden" icon="document-text" category="offerte">
            <a href="{{ route('quotes.create') }}" class="btn pri">+ Eerste offerte</a>
        </x-empty-state>
    @else
        <div class="card tw overflow-hidden">
            <table class="t">
                <thead>
                    <tr><th>Offerte</th><th>Project</th><th>Verzonden</th><th>Status</th><th class="r">Aanneemsom excl. btw</th></tr>
                </thead>
                <tbody>
                    @foreach ($quotes as $quote)
                        @php
                            $dossier = $quote->project ? route('projects.show', [$quote->project, 'tab' => 'offerte']) : route('quotes.show', $quote);
                            $open = $quote->daysOpen();
                        @endphp
                        <tr class="click" onclick="window.location = @js($dossier)">
                            <td>
                                <a href="{{ $dossier }}"><b>{{ $quote->number }}</b></a>
                                <span class="sub">versie {{ $quote->version }}</span>
                            </td>
                            <td>
                                {{ $quote->project?->name ?? $quote->customer->name }}
                                <span class="sub">{{ collect([$quote->project ? $quote->customer->name : null, $quote->lead?->service])->filter()->implode(', ') ?: 'nog geen project' }}</span>
                            </td>
                            <td class="tabular-nums">
                                @if ($quote->sent_at)
                                    {{ $quote->sent_at->translatedFormat('j M Y') }}
                                    @if ($open !== null)
                                        <span class="sub {{ $open >= 5 ? '!text-bad font-semibold' : '' }}">{{ $open.' dagen open'.($quote->viewed_count > 0 ? ', '.$quote->viewed_count.'× bekeken' : '') }}</span>
                                    @endif
                                @else
                                    <span class="text-muted">nog niet verzonden</span>
                                @endif
                            </td>
                            <td><x-status-badge :status="$quote->status" /></td>
                            <td class="r"><b>€ {{ number_format((float) $quote->subtotal, 2, ',', '.') }}</b></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="mt-4">
        {{ $quotes->links() }}
    </div>

</x-layouts.app>
