@php
    use App\Enums\InvoiceStatus;
    $eur = fn (float $v) => 'EUR '.number_format($v, 0, ',', '.');
@endphp

<x-layouts.app title="Facturatie">
    <x-page-header title="Facturatie" eyebrow="Geld" category="offerte"
                   subtitle="Termijnen uit de begroting, meerwerk en de eindafrekening." />

    <section data-motion-fan class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([
            ['Openstaand', $eur($outstanding), 'bij klanten', 'bg-brand-500', 'text-brand-400'],
            ['Waarvan te laat', $eur($overdue), $overdue > 0 ? 'direct opvolgen' : 'niets te laat', 'bg-red-500', 'text-red-400'],
            ['Klaar om te factureren', $eur($ready->sum('amount')), $ready->count().' '.($ready->count() === 1 ? 'termijn' : 'termijnen'), 'bg-project-500', 'text-project-400'],
            ['Facturen dit jaar', (string) $counts->sum(), 'in totaal', 'bg-lead-500', 'text-lead-400'],
        ] as [$label, $value, $sub, $accent, $labelClass])
            <div class="motion-lift overflow-hidden rounded-2xl border border-surface-700 bg-surface-800">
                <span class="block h-[3px] w-full {{ $accent }}"></span>
                <div class="p-4">
                    <p class="text-[11px] font-bold tracking-widest uppercase {{ $labelClass }}">{{ $label }}</p>
                    <p data-count class="mt-1 text-xl font-bold text-white">{{ $value }}</p>
                    <p class="mt-0.5 text-xs text-slate-400">{{ $sub }}</p>
                </div>
            </div>
        @endforeach
    </section>

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

    <div class="scrollbar-none mb-4 flex gap-2 overflow-x-auto">
        <a href="{{ route('invoices.index') }}"
           class="flex shrink-0 items-center gap-1.5 rounded-full border px-3.5 py-2 text-sm font-semibold transition {{ $filter === '' ? 'border-brand-500 bg-brand-500/10 text-white' : 'border-surface-700 text-slate-400 hover:text-slate-200' }}">
            Alles <span class="text-xs text-slate-500">{{ $counts->sum() }}</span>
        </a>
        @foreach (InvoiceStatus::cases() as $status)
            @continue(($counts[$status->value] ?? 0) === 0 && $filter !== $status->value)
            <a href="{{ route('invoices.index', ['status' => $status->value]) }}"
               class="flex shrink-0 items-center gap-1.5 rounded-full border px-3.5 py-2 text-sm font-semibold transition {{ $filter === $status->value ? 'border-brand-500 bg-brand-500/10 text-white' : 'border-surface-700 text-slate-400 hover:text-slate-200' }}">
                {{ $status->label() }} <span class="text-xs text-slate-500">{{ $counts[$status->value] ?? 0 }}</span>
            </a>
        @endforeach
    </div>

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
