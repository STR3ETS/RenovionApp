@php
    use App\Models\PurchaseOrder;
    $eur = fn (float $v) => 'EUR '.number_format($v, 0, ',', '.');
@endphp

<div class="grid gap-4 lg:grid-cols-2">
    {{-- Bestellingen --}}
    <div>
        <h2 class="mb-3 text-sm font-bold text-white">Bestellingen</h2>

        @if ($orders->isEmpty())
            <x-empty-state icon="shopping-cart" category="offerte" title="Niets besteld"
                           subtitle="Een bestelling houdt bij wat er onderweg is naar de bouwplaats." />
        @else
            <div class="space-y-2">
                @foreach ($orders as $order)
                    <a href="{{ route('purchase-orders.show', $order) }}"
                       class="flex flex-wrap items-center gap-3 rounded-2xl border bg-surface-800 p-4 transition hover:border-surface-600 {{ $order->isLate() ? 'border-red-500/30' : 'border-surface-700' }}">
                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-center gap-2">
                                <b class="truncate text-sm font-semibold text-white">{{ $order->supplier }}</b>
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $order->badgeClasses() }}">{{ $order->statusLabel() }}</span>
                                @if ($order->isLate())
                                    <span class="rounded-full bg-red-500/15 px-2 py-0.5 text-[10px] font-bold text-red-300">Te laat</span>
                                @endif
                            </span>
                            <span class="mt-0.5 block truncate text-xs text-slate-400">
                                {{ $order->number }},
                                {{ $order->expected_on ? 'levering '.$order->expected_on->translatedFormat('j M') : 'geen leverdatum' }}
                                @if ($order->invoices->isEmpty())
                                    , nog geen factuur
                                @endif
                            </span>
                        </span>
                        <span class="shrink-0 text-sm font-bold text-white">{{ $eur($order->total()) }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Prijsaanvragen en partners --}}
    <div>
        <h2 class="mb-3 text-sm font-bold text-white">Prijsaanvragen</h2>

        @if ($rfqs->isEmpty())
            <x-empty-state icon="scale" category="klant" title="Niets uitbesteed"
                           subtitle="Zet een stuk werk uit bij een paar partners en vergelijk de biedingen tegen de begroting." />
        @else
            <div class="space-y-2">
                @foreach ($rfqs as $rfq)
                    <a href="{{ route('rfqs.show', $rfq) }}"
                       class="flex flex-wrap items-center gap-3 rounded-2xl border border-surface-700 bg-surface-800 p-4 transition hover:border-surface-600">
                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-center gap-2">
                                <b class="truncate text-sm font-semibold text-white">{{ $rfq->title }}</b>
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $rfq->badgeClasses() }}">{{ $rfq->statusLabel() }}</span>
                            </span>
                            <span class="mt-0.5 block truncate text-xs text-slate-400">
                                {{ $rfq->number }}, {{ $rfq->bids->count() }} {{ $rfq->bids->count() === 1 ? 'bieding' : 'biedingen' }}
                                @if ($rfq->winner), gegund aan {{ $rfq->winner->name }} @endif
                            </span>
                        </span>

                        <span class="shrink-0 text-right">
                            <b class="block text-sm font-bold text-white">{{ $eur((float) $rfq->budget) }}</b>
                            @if ($rfq->savings() !== null)
                                <small class="block text-xs {{ $rfq->savings() >= 0 ? 'text-green-400' : 'text-red-400' }}">
                                    {{ $eur(abs($rfq->savings())) }} {{ $rfq->savings() >= 0 ? 'onder' : 'boven' }}
                                </small>
                            @else
                                <small class="block text-xs text-slate-500">begroot</small>
                            @endif
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
