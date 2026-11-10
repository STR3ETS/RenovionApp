@php
    use App\Enums\InvoiceStatus;
    $eur = fn (float $v) => 'EUR '.number_format($v, 2, ',', '.');
    $s = $settlement;
@endphp

<div class="grid gap-4 lg:grid-cols-3">
    <div class="space-y-4 lg:col-span-2">
        {{-- Facturen --}}
        <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
            <h2 class="mb-4 text-sm font-bold text-white">Facturen</h2>

            @if ($invoices->isEmpty())
                <p class="text-sm text-slate-500">Nog niets gefactureerd op dit project.</p>
            @else
                <div class="space-y-1.5">
                    @foreach ($invoices as $invoice)
                        <a href="{{ route('invoices.show', $invoice) }}"
                           class="flex flex-wrap items-center gap-3 rounded-xl border border-surface-700 bg-surface-850 p-3 transition hover:border-surface-600">
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-2">
                                    <b class="truncate text-sm font-semibold text-white">{{ $invoice->description }}</b>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $invoice->status->badgeClasses() }}">{{ $invoice->status->label() }}</span>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $invoice->type->badgeClasses() }}">{{ $invoice->type->label() }}</span>
                                </span>
                                <span class="mt-0.5 block truncate text-xs text-slate-400">
                                    {{ $invoice->number }},
                                    @if ($invoice->isOverdue())
                                        <span class="font-semibold text-red-400">{{ $invoice->daysOverdue() }} dagen over de vervaldatum</span>
                                    @else
                                        vervalt {{ $invoice->due_date->translatedFormat('j M Y') }}
                                    @endif
                                </span>
                            </span>
                            <span class="shrink-0 text-sm font-bold text-white">{{ $eur($invoice->total()) }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Minderwerk --}}
        @if ($deductions->isNotEmpty())
            <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-3 text-sm font-bold text-white">Minder<span class="text-brand-500">werk</span></h2>
                <div class="space-y-1.5">
                    @foreach ($deductions as $deduction)
                        <div class="flex items-center gap-3 text-sm">
                            <span class="min-w-0 flex-1 truncate text-slate-200">{{ $deduction->description }}</span>
                            <span class="shrink-0 rounded-full bg-white/10 px-2 py-0.5 text-[10px] font-bold text-slate-300">{{ $deduction->statusLabel() }}</span>
                            <span class="shrink-0 font-semibold text-amber-300">-{{ $eur((float) $deduction->amount) }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>

    {{-- De eindafrekening --}}
    <div>
        <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
            <h2 class="mb-1 text-sm font-bold text-white">Eind<span class="text-brand-500">afrekening</span></h2>
            <p class="mb-4 text-xs text-slate-500">Wat er na alles verrekend nog openstaat.</p>

            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-400">Aanneemsom</dt>
                    <dd class="font-semibold text-white">{{ $eur($s['base']) }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-400">Meerwerk akkoord</dt>
                    <dd class="font-semibold text-green-400">+{{ $eur($s['extras']) }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-400">Minderwerk akkoord</dt>
                    <dd class="font-semibold text-amber-300">-{{ $eur($s['deduction_total']) }}</dd>
                </div>
                <div class="flex justify-between gap-3 border-t border-surface-700 pt-2">
                    <dt class="font-semibold text-slate-300">Eindbedrag</dt>
                    <dd class="font-bold text-white">{{ $eur($s['final']) }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-400">Al gefactureerd</dt>
                    <dd class="font-semibold text-slate-300">-{{ $eur($s['invoiced']) }}</dd>
                </div>
                <div class="flex justify-between gap-3 border-t border-surface-700 pt-2 text-base">
                    <dt class="font-bold text-white">Slottermijn</dt>
                    <dd class="font-bold text-white">{{ $eur($s['balance']) }}</dd>
                </div>
                @if ($s['retention'] > 0)
                    <div class="flex justify-between gap-3">
                        <dt class="text-amber-300">In te houden, vijf procent</dt>
                        <dd class="font-semibold text-amber-300">{{ $eur($s['retention']) }}</dd>
                    </div>
                    <p class="text-xs text-slate-500">
                        Vrij te geven {{ $s['retention_release_on']->translatedFormat('j F Y') }}, drie maanden na oplevering.
                    </p>
                @endif
            </dl>

            @can('manage-crm')
                @if ($s['existing'])
                    <a href="{{ route('invoices.show', $s['existing']) }}"
                       class="mt-4 block w-full rounded-xl border border-surface-600 py-2.5 text-center text-sm font-semibold text-slate-200 transition hover:text-white">
                        Eindafrekening openen
                    </a>
                @else
                    <form method="POST" action="{{ route('invoices.final', $project) }}" class="mt-4">
                        @csrf
                        <button type="submit" class="w-full rounded-xl bg-brand-500 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600">
                            Eindafrekening opstellen
                        </button>
                    </form>
                @endif
            @endcan
        </section>
    </div>
</div>
