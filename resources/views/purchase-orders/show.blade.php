@php
    use App\Models\PurchaseOrder;
    $eur = fn (float $v) => 'EUR '.number_format($v, 2, ',', '.');
@endphp

<x-layouts.app :title="$order->number">
    <div class="mb-4">
        <a href="{{ route('purchase-orders.index') }}" class="flex items-center gap-1 text-sm font-semibold text-slate-400 transition hover:text-white">
            <x-icon name="chevron-left" class="h-4 w-4" /> Inkoop
        </a>
    </div>

    <x-page-header :title="$order->supplier" :eyebrow="$order->number" category="offerte"
                   :subtitle="$order->project->name.', '.(\App\Models\PurchaseInvoice::CATEGORIES[$order->category] ?? $order->category)">
        <span class="rounded-full px-3 py-1.5 text-xs font-bold {{ $order->badgeClasses() }}">{{ $order->statusLabel() }}</span>

        @foreach (PurchaseOrder::STATUSES as $status => $label)
            @continue($status === $order->status)
            <form method="POST" action="{{ route('purchase-orders.update', $order) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="{{ $status }}">
                <button type="submit" class="rounded-full border border-surface-600 px-3.5 py-2 text-sm font-semibold text-slate-300 transition hover:text-white">{{ $label }}</button>
            </form>
        @endforeach
    </x-page-header>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-4 text-sm font-bold text-white">Bestelde <span class="text-brand-500">regels</span></h2>

                @if ($order->lines->isEmpty())
                    <p class="text-sm text-slate-500">Nog geen regels op deze bestelling.</p>
                @else
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-surface-700 text-[10px] font-bold tracking-wider text-slate-500 uppercase">
                                <th class="pb-2 text-left">Omschrijving</th>
                                <th class="pb-2 text-right">Aantal</th>
                                <th class="pb-2 text-right">Prijs</th>
                                <th class="pb-2 text-right">Totaal</th>
                                <th class="w-8"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($order->lines as $line)
                                <tr class="border-b border-surface-700/60">
                                    <td class="py-2.5 text-slate-200">{{ $line->description }}</td>
                                    <td class="py-2.5 text-right text-slate-400">
                                        {{ rtrim(rtrim(number_format((float) $line->quantity, 2, ',', '.'), '0'), ',') }} {{ $line->unit }}
                                    </td>
                                    <td class="py-2.5 text-right text-slate-400">{{ $eur((float) $line->unit_price) }}</td>
                                    <td class="py-2.5 text-right font-semibold text-white">{{ $eur($line->total()) }}</td>
                                    <td class="py-2.5 text-right">
                                        <form method="POST" action="{{ route('purchase-order-lines.destroy', $line) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" title="Verwijderen" class="text-slate-600 transition hover:text-red-400">
                                                <x-icon name="trash" class="h-3.5 w-3.5" />
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="mt-3 flex justify-between border-t border-surface-700 pt-3">
                        <span class="text-sm font-bold text-white">Totaal excl. btw</span>
                        <span class="text-sm font-bold text-white">{{ $eur($order->total()) }}</span>
                    </div>
                @endif

                <form method="POST" action="{{ route('purchase-order-lines.store', $order) }}" class="mt-4 flex flex-wrap gap-2 border-t border-surface-700 pt-4">
                    @csrf
                    <input type="text" name="description" required placeholder="Wat bestel je?"
                           class="min-w-0 flex-1 rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">
                    <input type="text" name="quantity" value="1" title="Aantal"
                           class="w-16 shrink-0 rounded-xl border-surface-600 bg-surface-850 py-2 text-right text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                    <input type="text" name="unit" value="st" title="Eenheid"
                           class="w-16 shrink-0 rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                    <input type="text" name="unit_price" placeholder="Prijs" title="Prijs per eenheid"
                           class="w-24 shrink-0 rounded-xl border-surface-600 bg-surface-850 py-2 text-right text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">
                    <button type="submit" class="shrink-0 rounded-xl bg-brand-500 px-3.5 py-2 text-white transition hover:bg-brand-600">
                        <x-icon name="plus" class="h-4 w-4" />
                    </button>
                </form>
            </div>
        </div>

        <div class="space-y-4">
            <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-3 text-sm font-bold text-white">Levering</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-400">Besteld op</dt><dd class="text-slate-200">{{ $order->ordered_on?->translatedFormat('j M Y') ?? 'niet vastgelegd' }}</dd></div>
                    <div class="flex justify-between">
                        <dt class="text-slate-400">Verwacht</dt>
                        <dd class="{{ $order->isLate() ? 'font-semibold text-red-400' : 'text-slate-200' }}">{{ $order->expected_on?->translatedFormat('j M Y') ?? 'onbekend' }}</dd>
                    </div>
                    @if ($order->delivered_on)
                        <div class="flex justify-between"><dt class="text-slate-400">Geleverd</dt><dd class="text-green-300">{{ $order->delivered_on->translatedFormat('j M Y') }}</dd></div>
                    @endif
                </dl>
            </div>

            @if ($order->invoices->isNotEmpty())
                <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                    <h2 class="mb-3 text-sm font-bold text-white">Facturen hierop</h2>
                    <div class="space-y-2">
                        @foreach ($order->invoices as $invoice)
                            <div class="flex items-center justify-between gap-2 text-sm">
                                <span class="min-w-0 truncate text-slate-200">{{ $invoice->number }}</span>
                                <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $invoice->badgeClasses() }}">{{ $invoice->statusLabel() }}</span>
                                <span class="shrink-0 font-semibold text-white">{{ $eur($invoice->total()) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="rounded-2xl border border-amber-500/25 bg-amber-500/10 p-5">
                    <p class="text-sm text-slate-200">
                        <b class="text-white">Nog geen factuur.</b> Deze bestelling telt als verplichting mee in de nacalculatie.
                    </p>
                </div>
            @endif

            @if ($order->notes)
                <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                    <h2 class="mb-2 text-sm font-bold text-white">Notities</h2>
                    <p class="text-sm whitespace-pre-line text-slate-300">{{ $order->notes }}</p>
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
