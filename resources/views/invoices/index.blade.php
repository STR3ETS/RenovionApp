@php
    use App\Enums\InvoiceStatus;
    $eur = fn (float $v) => 'EUR '.number_format($v, 0, ',', '.');
@endphp

<x-layouts.app title="Facturatie">
    <x-page-header title="Facturatie" eyebrow="Geld" category="offerte"
                   subtitle="Termijnen uit de begroting, meerwerk en de eindafrekening." />

    <x-kpi-row>
        <x-kpi dark label="Openstaand bij klanten" :value="$eur($outstanding)"
               :sub="$counts->sum().' facturen in totaal'" />
        <x-kpi label="Waarvan te laat" :value="$eur($overdue)" :alert="$overdue > 0"
               :sub="$overdue > 0 ? 'direct opvolgen' : 'niets te laat'" />
        <x-kpi label="Klaar om te factureren" :value="$eur($ready->sum('amount'))"
               :sub="$ready->count().' '.($ready->count() === 1 ? 'termijn' : 'termijnen')" />
        <x-kpi label="Betaald" :value="(string) ($counts[\App\Enums\InvoiceStatus::Betaald->value] ?? 0)" sub="facturen voldaan" />
    </x-kpi-row>

    @if ($ready->isNotEmpty())
        <div class="mb-5 rounded-2xl border border-project-500/25 bg-project-500/5 p-5">
            <h2 class="mb-1 text-sm font-bold text-white">Klaar om te <span class="text-brand-500">factureren</span></h2>
            <p class="mb-4 text-xs text-slate-400">Termijnen uit definitieve begrotingen waarvoor nog geen factuur is.</p>

            <div class="space-y-2">
                @foreach ($ready as $row)
                    <div class="flex flex-wrap items-center gap-3 rounded-xl border border-surface-700 bg-surface-800 p-3">
                        <span class="min-w-0 flex-1">
                            <b class="block truncate text-sm font-semibold text-white">{{ $row['term']->description }}</b>
                            <small class="block truncate text-xs text-slate-400">
                                {{ $row['budget']->project?->name }},
                                {{ rtrim(rtrim(number_format((float) $row['term']->percentage, 2, ',', '.'), '0'), ',') }}% van de aanneemsom
                            </small>
                        </span>
                        <span class="shrink-0 text-sm font-bold text-white">{{ $eur($row['amount']) }}</span>
                        <form method="POST" action="{{ route('invoices.term', $row['term']) }}" class="shrink-0">
                            @csrf
                            <button type="submit" class="rounded-full bg-brand-500 px-4 py-2 text-sm font-bold text-white transition hover:bg-brand-600">
                                Factuur maken
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <x-tabs>
        <x-tab :href="route('invoices.index')" :active="$filter === ''" :count="$counts->sum()">Alles</x-tab>
        @foreach (InvoiceStatus::cases() as $status)
            @continue(($counts[$status->value] ?? 0) === 0 && $filter !== $status->value)
            <x-tab :href="route('invoices.index', ['status' => $status->value])"
                   :active="$filter === $status->value" :count="$counts[$status->value] ?? 0">
                {{ $status->label() }}
            </x-tab>
        @endforeach
    </x-tabs>

    @if ($invoices->isEmpty())
        <x-empty-state icon="currency-euro" category="offerte" title="Nog geen facturen"
                       subtitle="Zodra een begroting definitief is, kun je de termijnen hier factureren." />
    @else
        <div class="overflow-hidden rounded-2xl border border-surface-700 bg-surface-800">
            @foreach ($invoices as $invoice)
                <a href="{{ route('invoices.show', $invoice) }}"
                   class="flex items-center gap-3 border-b border-surface-700 border-l-4 p-4 transition last:border-b-0 hover:bg-white/5 {{ $invoice->isOverdue() ? 'border-l-red-500' : 'border-l-transparent' }}">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-offerte-500/15 text-offerte-400">
                        <x-icon name="receipt" class="h-5 w-5" />
                    </span>

                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center gap-2">
                            <b class="truncate text-sm font-semibold text-white">{{ $invoice->description }}</b>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $invoice->status->badgeClasses() }}">{{ $invoice->status->label() }}</span>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $invoice->type->badgeClasses() }}">{{ $invoice->type->label() }}</span>
                        </span>
                        <small class="mt-0.5 block truncate text-xs text-slate-400">
                            {{ $invoice->number }}, {{ $invoice->customer->name }},
                            @if ($invoice->isOverdue())
                                <span class="font-semibold text-red-400">{{ $invoice->daysOverdue() }} dagen over de vervaldatum</span>
                            @else
                                vervalt {{ $invoice->due_date->translatedFormat('j M') }}
                            @endif
                        </small>
                    </span>

                    <span class="shrink-0 text-right">
                        <b class="block text-sm font-bold text-white">{{ $eur($invoice->total()) }}</b>
                        <small class="block text-xs text-slate-500">incl. btw</small>
                    </span>
                </a>
            @endforeach
        </div>

        <div class="mt-4">{{ $invoices->links() }}</div>
    @endif
</x-layouts.app>
