@php
    $eur = fn (float $v) => 'EUR '.number_format($v, 0, ',', '.');
    $f = $finance;
    $maxCash = max(1, collect($f['cashflow'])->flatMap(fn ($m) => [$m['in'], $m['out']])->max() ?: 1);
    $maxRev = max(1, collect($f['revenue_per_month'])->max() ?: 1);
@endphp

<x-layouts.app title="Financieel">
    <x-page-header title="Financieel" eyebrow="Geld" category="offerte"
                   subtitle="Omzet, werkvoorraad, debiteuren en crediteuren, met een prognose voor zes maanden." />

    <x-kpi-row class="xl:grid-cols-[1.5fr_repeat(5,minmax(0,1fr))]">
        <x-kpi dark label="Werkvoorraad" :value="$eur($f['order_book'])" :sub="$eur($f['revenue_year']).' omzet dit jaar'" />
        <x-kpi label="Nog te factureren" :value="$eur($f['uninvoiced'])" sub="uit termijnen" />
        <x-kpi label="Debiteuren" :value="$eur($f['debtors'])" :alert="$f['debtors_overdue'] > 0"
               :sub="$f['debtors_overdue'] > 0 ? $eur($f['debtors_overdue']).' te laat' : 'niets te laat'" />
        <x-kpi label="Crediteuren" :value="$eur($f['creditors'])" :alert="$f['creditors_overdue'] > 0"
               :sub="$f['creditors_overdue'] > 0 ? $eur($f['creditors_overdue']).' te laat' : 'niets te laat'" />
        <x-kpi label="Verplichtingen" :value="$eur($f['commitments'])" sub="besteld, nog geen factuur" />
        <x-kpi label="Pipeline gewogen" :value="$eur($f['pipeline'])" :sub="$eur($f['open_quotes']).' aan offertes'" />
    </x-kpi-row>

    <div class="grid gap-4 lg:grid-cols-2">
        {{-- Omzet per maand, zes maanden terug --}}
        <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
            <h2 class="mb-4 text-sm font-bold text-white">Omzet <span class="text-brand-500">per maand</span></h2>

            <div class="flex h-40 items-end gap-2">
                @foreach ($f['revenue_per_month'] as $month => $amount)
                    <div class="flex flex-1 flex-col items-center gap-2">
                        <span class="text-[10px] font-semibold text-slate-400">{{ $amount > 0 ? number_format($amount / 1000, 0, ',', '.').'k' : '' }}</span>
                        <span class="w-full rounded-t bg-brand-500/70" style="height: {{ max(2, round($amount / $maxRev * 100)) }}%"></span>
                        <span class="text-[10px] text-slate-500">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->translatedFormat('M') }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Cashflow: wat er in en uit gaat --}}
        <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
            <h2 class="mb-1 text-sm font-bold text-white">Cashflow <span class="text-brand-500">zes maanden</span></h2>
            <p class="mb-4 text-xs text-slate-500">Een prognose, geen boekhouding. Inkomsten op vervaldatum, uitgaven op leverdatum.</p>

            <div class="flex h-40 items-end gap-3">
                @foreach ($f['cashflow'] as $month)
                    <div class="flex flex-1 flex-col items-center gap-2">
                        <span class="flex w-full flex-1 items-end gap-0.5">
                            <span class="flex-1 rounded-t bg-green-500/70" style="height: {{ max(2, round($month['in'] / $maxCash * 100)) }}%" title="In {{ $eur($month['in']) }}"></span>
                            <span class="flex-1 rounded-t bg-red-500/60" style="height: {{ max(2, round($month['out'] / $maxCash * 100)) }}%" title="Uit {{ $eur($month['out']) }}"></span>
                        </span>
                        <span class="text-[10px] font-semibold {{ $month['net'] < 0 ? 'text-red-400' : 'text-slate-400' }}">
                            {{ $month['net'] >= 0 ? '+' : '' }}{{ number_format($month['net'] / 1000, 0, ',', '.') }}k
                        </span>
                        <span class="text-[10px] text-slate-500">{{ $month['label'] }}</span>
                    </div>
                @endforeach
            </div>

            <div class="mt-3 flex gap-4 border-t border-surface-700 pt-3 text-xs">
                <span class="flex items-center gap-1.5 text-slate-400"><span class="h-2 w-2 rounded-full bg-green-500/70"></span> Binnen</span>
                <span class="flex items-center gap-1.5 text-slate-400"><span class="h-2 w-2 rounded-full bg-red-500/60"></span> Uit</span>
            </div>
        </div>

        {{-- Debiteuren --}}
        <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
            <h2 class="mb-4 text-sm font-bold text-white">Debiteuren</h2>

            @if ($f['debtor_invoices']->isEmpty())
                <p class="text-sm text-slate-500">Geen openstaande facturen.</p>
            @else
                <div class="space-y-2">
                    @foreach ($f['debtor_invoices']->take(8) as $invoice)
                        <a href="{{ route('invoices.show', $invoice) }}" class="flex items-center gap-3 rounded-xl px-2 py-2 transition hover:bg-white/5">
                            <span class="h-8 w-1 shrink-0 rounded-full {{ $invoice->isOverdue() ? 'bg-red-500' : 'bg-surface-600' }}"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-white">{{ $invoice->customer->name }}</span>
                                <span class="block truncate text-xs text-slate-500">
                                    {{ $invoice->number }},
                                    {{ $invoice->isOverdue() ? $invoice->daysOverdue().' dagen te laat' : 'vervalt '.$invoice->due_date->translatedFormat('j M') }}
                                </span>
                            </span>
                            <span class="shrink-0 text-sm font-bold text-white">{{ $eur($invoice->openAmount()) }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Crediteuren --}}
        <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
            <h2 class="mb-1 text-sm font-bold text-white">Crediteuren</h2>
            <p class="mb-4 text-xs text-slate-500">Plus {{ $eur($f['commitments']) }} aan bestellingen waarvan de factuur nog moet komen.</p>

            @if ($f['creditor_invoices']->isEmpty())
                <p class="text-sm text-slate-500">Geen openstaande inkoopfacturen.</p>
            @else
                <div class="space-y-2">
                    @foreach ($f['creditor_invoices']->take(8) as $invoice)
                        <div class="flex items-center gap-3 rounded-xl px-2 py-2">
                            <span class="h-8 w-1 shrink-0 rounded-full {{ $invoice->isOverdue() ? 'bg-red-500' : 'bg-surface-600' }}"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-white">{{ $invoice->supplier }}</span>
                                <span class="block truncate text-xs text-slate-500">
                                    {{ $invoice->number }}, {{ $invoice->categoryLabel() }},
                                    {{ $invoice->isOverdue() ? 'te laat' : 'vervalt '.$invoice->due_date->translatedFormat('j M') }}
                                </span>
                            </span>
                            <span class="shrink-0 text-sm font-bold text-white">{{ $eur($invoice->total()) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
