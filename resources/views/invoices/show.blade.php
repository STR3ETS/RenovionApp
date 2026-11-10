@php
    use App\Enums\InvoiceStatus;
    $eur = fn (float $v) => 'EUR '.number_format($v, 2, ',', '.');
@endphp

<x-layouts.app :title="$invoice->number">
    <div class="mb-4">
        <a href="{{ route('invoices.index') }}" class="flex items-center gap-1 text-sm font-semibold text-slate-400 transition hover:text-white">
            <x-icon name="chevron-left" class="h-4 w-4" /> Facturatie
        </a>
    </div>

    <x-page-header :title="$invoice->description" :eyebrow="$invoice->number" category="offerte"
                   :subtitle="$invoice->customer->name.($invoice->project ? ', '.$invoice->project->name : '')">
        <span class="rounded-full px-3 py-1.5 text-xs font-bold {{ $invoice->status->badgeClasses() }}">{{ $invoice->status->label() }}</span>
    </x-page-header>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-surface-700 text-[10px] font-bold tracking-wider text-slate-500 uppercase">
                            <th class="pb-2 text-left">Omschrijving</th>
                            <th class="pb-2 text-right">Aantal</th>
                            <th class="pb-2 text-right">Prijs</th>
                            <th class="pb-2 text-right">Totaal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoice->lines as $line)
                            <tr class="border-b border-surface-700/60">
                                <td class="py-2.5 text-slate-200">{{ $line->description }}</td>
                                <td class="py-2.5 text-right text-slate-400">
                                    {{ rtrim(rtrim(number_format((float) $line->quantity, 2, ',', '.'), '0'), ',') }} {{ $line->unit }}
                                </td>
                                <td class="py-2.5 text-right text-slate-400">{{ $eur((float) $line->unit_price) }}</td>
                                <td class="py-2.5 text-right font-semibold {{ $line->total() < 0 ? 'text-amber-300' : 'text-white' }}">{{ $eur($line->total()) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="mt-4 space-y-1.5 border-t border-surface-700 pt-4 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-400">Subtotaal</span>
                        <span class="font-semibold text-white">{{ $eur((float) $invoice->amount) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Btw {{ rtrim(rtrim(number_format((float) $invoice->vat_pct, 2, ',', '.'), '0'), ',') }}%</span>
                        <span class="font-semibold text-white">{{ $eur($invoice->vatAmount()) }}</span>
                    </div>
                    @if ((float) $invoice->retention > 0)
                        <div class="flex justify-between">
                            <span class="text-amber-300">Ingehouden, opschortingsrecht vijf procent</span>
                            <span class="font-semibold text-amber-300">-{{ $eur((float) $invoice->retention) }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between border-t border-surface-700 pt-2 text-base">
                        <span class="font-bold text-white">Te voldoen</span>
                        <span class="font-bold text-white">{{ $eur($invoice->total()) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-3 text-sm font-bold text-white">Status</h2>

                <dl class="mb-4 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-400">Factuurdatum</dt><dd class="text-slate-200">{{ $invoice->date->translatedFormat('j M Y') }}</dd></div>
                    <div class="flex justify-between">
                        <dt class="text-slate-400">Vervaldatum</dt>
                        <dd class="{{ $invoice->isOverdue() ? 'font-semibold text-red-400' : 'text-slate-200' }}">{{ $invoice->due_date->translatedFormat('j M Y') }}</dd>
                    </div>
                    @if ($invoice->paid_at)
                        <div class="flex justify-between"><dt class="text-slate-400">Betaald op</dt><dd class="text-green-300">{{ $invoice->paid_at->translatedFormat('j M Y') }}</dd></div>
                    @endif
                </dl>

                @if ($invoice->status === InvoiceStatus::Concept)
                    <form method="POST" action="{{ route('invoices.update', $invoice) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="{{ InvoiceStatus::Verzonden->value }}">
                        <button type="submit" class="flex w-full items-center justify-center gap-1.5 rounded-xl bg-brand-500 py-3 text-sm font-bold text-white transition hover:bg-brand-600">
                            <x-icon name="paper-airplane" class="h-4 w-4" /> Markeren als verstuurd
                        </button>
                    </form>
                @elseif ($invoice->status === InvoiceStatus::Verzonden)
                    <form method="POST" action="{{ route('invoices.update', $invoice) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="{{ InvoiceStatus::Betaald->value }}">
                        <button type="submit" class="flex w-full items-center justify-center gap-1.5 rounded-xl bg-brand-500 py-3 text-sm font-bold text-white transition hover:bg-brand-600">
                            <x-icon name="check" class="h-4 w-4" /> Betaling ontvangen
                        </button>
                    </form>
                @endif

                @if ($invoice->status === InvoiceStatus::Concept)
                    <form method="POST" action="{{ route('invoices.destroy', $invoice) }}" class="mt-2">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="w-full rounded-xl border border-surface-600 py-2.5 text-sm font-semibold text-slate-400 transition hover:text-red-400">
                            Concept verwijderen
                        </button>
                    </form>
                @endif
            </div>

            @if ($invoice->term)
                <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                    <h2 class="mb-2 text-sm font-bold text-white">Uit de begroting</h2>
                    <p class="text-sm text-slate-300">{{ $invoice->term->description }}</p>
                    <a href="{{ route('budgets.show', $invoice->term->budget) }}" class="mt-2 inline-flex items-center gap-1 text-sm font-semibold text-brand-400 transition hover:text-brand-300">
                        {{ $invoice->term->budget->number }} <x-icon name="arrow-right" class="h-3.5 w-3.5" />
                    </a>
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
