@php
    use App\Enums\InvoiceStatus;
    $eur = fn (float $v) => '€ '.number_format($v, 0, ',', '.');
@endphp

<x-layouts.app title="Facturatie">
    <x-page-header title="Facturatie"
                   subtitle="Termijnfacturen uit de ondertekende betalingsschema's. De betaalstatus werkt direct door in het projectdossier." />

    <x-kpi-row>
        <x-kpi dark label="Openstaand incl. btw" :value="$eur($outstanding)"
               :sub="$counts->sum().' facturen in totaal'" />
        <x-kpi label="Vervallen" :value="$eur($overdue)" :alert="$overdue > 0"
               :sub="$overdue > 0 ? 'direct opvolgen' : 'niets te laat'" />
        <x-kpi label="Klaar om te factureren" :value="$eur($ready->sum('amount'))"
               :sub="$ready->count().' '.($ready->count() === 1 ? 'termijn' : 'termijnen')" />
        <x-kpi label="Betaald" :value="(string) ($counts[\App\Enums\InvoiceStatus::Betaald->value] ?? 0)" sub="facturen voldaan" />
    </x-kpi-row>

    @if ($ready->isNotEmpty())
        <div class="mb-5 rounded-2xl border border-project-500/25 bg-project-500/5 p-5">
            <h2 class="mb-1 text-sm font-bold text-ink">Klaar om te <span class="text-brand-500">factureren</span></h2>
            <p class="mb-4 text-xs text-muted">Termijnen uit definitieve begrotingen waarvoor nog geen factuur is.</p>

            <div class="space-y-2">
                @foreach ($ready as $row)
                    <div class="flex flex-wrap items-center gap-3 rounded-xl border border-surface-700 bg-surface-800 p-3">
                        <span class="min-w-0 flex-1">
                            <b class="block truncate text-sm font-semibold text-ink">{{ $row['term']->description }}</b>
                            <small class="block truncate text-xs text-muted">
                                {{ $row['budget']->project?->name }},
                                {{ rtrim(rtrim(number_format((float) $row['term']->percentage, 2, ',', '.'), '0'), ',') }}% van de aanneemsom
                            </small>
                        </span>
                        <span class="shrink-0 text-sm font-bold text-ink">{{ $eur($row['amount']) }}</span>
                        <form method="POST" action="{{ route('invoices.term', $row['term']) }}" class="shrink-0">
                            @csrf
                            <button type="submit" class="btn pri">
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
        <div class="card tw overflow-hidden">
            <table class="t">
                <thead>
                    <tr><th>Factuur</th><th>Klant, project</th><th>Termijn</th><th>Vervaldatum</th><th class="r">Incl. btw</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @foreach ($invoices as $invoice)
                        <tr class="click" onclick="window.location = @js(route('invoices.show', $invoice))">
                            <td>
                                <a href="{{ route('invoices.show', $invoice) }}"><b>{{ $invoice->number }}</b></a>
                                <span class="sub">{{ $invoice->date?->translatedFormat('j M') }}</span>
                            </td>
                            <td>
                                {{ $invoice->customer->name }}
                                <span class="sub">{{ $invoice->project?->name ?? $invoice->type->label() }}</span>
                            </td>
                            <td>{{ $invoice->description }}</td>
                            <td class="tabular-nums">
                                {{ $invoice->due_date->translatedFormat('j M') }}
                                @if ($invoice->isOverdue())
                                    <span class="sub !text-bad font-semibold">{{ $invoice->daysOverdue() }} dagen te laat</span>
                                @endif
                            </td>
                            <td class="r"><b>€ {{ number_format($invoice->total(), 2, ',', '.') }}</b></td>
                            <td>
                                @if ($invoice->isOverdue())
                                    <span class="pill bad">Vervallen</span>
                                @else
                                    <x-status-badge :status="$invoice->status" />
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $invoices->links() }}</div>
    @endif
</x-layouts.app>
