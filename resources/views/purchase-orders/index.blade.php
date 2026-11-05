@php
    use App\Models\PurchaseInvoice;
    use App\Models\PurchaseOrder;
    $eur = fn (float $v) => 'EUR '.number_format($v, 0, ',', '.');
@endphp

<x-layouts.app title="Inkoop">
    <x-page-header title="Inkoop" eyebrow="Geld" category="offerte"
                   subtitle="Bestellingen bij leveranciers en de facturen die erop volgen.">
        <div x-data="{ open: false }" class="relative">
            <button @click="open = !open" @click.outside="open = false"
                    class="flex items-center gap-1.5 rounded-full bg-brand-500 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600">
                <x-icon name="plus" class="h-4 w-4" /> Bestelling
            </button>

            <form x-show="open" x-cloak method="POST" action="{{ route('purchase-orders.store') }}"
                  class="absolute right-0 z-20 mt-2 w-80 space-y-3 rounded-2xl border border-surface-700 bg-surface-800 p-4 shadow-2xl shadow-black/40">
                @csrf
                <x-field label="Project" name="project_id">
                    <select name="project_id" id="project_id" required
                            class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}">{{ $project->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Leverancier" name="supplier">
                    <input type="text" name="supplier" id="supplier" required
                           class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Soort" name="category">
                    <select name="category" id="category"
                            class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                        @foreach (PurchaseInvoice::CATEGORIES as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Verwachte levering" name="expected_on">
                    <input type="date" name="expected_on" id="expected_on"
                           class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <button type="submit" class="w-full rounded-xl bg-brand-500 py-2.5 text-sm font-bold text-white">Aanmaken</button>
            </form>
        </div>
    </x-page-header>

    <x-kpi-row class="lg:grid-cols-[1.5fr_repeat(2,minmax(0,1fr))]">
        <x-kpi dark label="Openstaande bestellingen" :value="$eur($open)" sub="nog niet gefactureerd" />
        <x-kpi label="Facturen ter goedkeuring" :value="(string) $toApprove" :alert="$toApprove > 0"
               :sub="$toApprove > 0 ? 'wachten op controle' : 'alles beoordeeld'" />
        <x-kpi label="Bestellingen totaal" :value="(string) $orders->total()" sub="in de lijst" />
    </x-kpi-row>

    <div class="grid gap-4 xl:grid-cols-2">
        <div>
            <h2 class="mb-3 text-sm font-bold text-white">Bestellingen</h2>

            @if ($orders->isEmpty())
                <x-empty-state icon="shopping-cart" category="offerte" title="Nog geen bestellingen"
                               subtitle="Een bestelling houdt bij wat er onderweg is naar de bouwplaats." />
            @else
                <div class="overflow-hidden rounded-2xl border border-surface-700 bg-surface-800">
                    @foreach ($orders as $order)
                        <a href="{{ route('purchase-orders.show', $order) }}"
                           class="flex items-center gap-3 border-b border-surface-700 border-l-4 p-4 transition last:border-b-0 hover:bg-white/5 {{ $order->isLate() ? 'border-l-red-500' : 'border-l-transparent' }}">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-offerte-500/15 text-offerte-400">
                                <x-icon name="truck" class="h-5 w-5" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-2">
                                    <b class="truncate text-sm font-semibold text-white">{{ $order->supplier }}</b>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $order->badgeClasses() }}">{{ $order->statusLabel() }}</span>
                                    @if ($order->isLate())
                                        <span class="rounded-full bg-red-500/15 px-2 py-0.5 text-[10px] font-bold text-red-300">Te laat</span>
                                    @endif
                                </span>
                                <small class="mt-0.5 block truncate text-xs text-slate-400">
                                    {{ $order->number }}, {{ $order->project->name }},
                                    {{ $order->expected_on ? 'levering '.$order->expected_on->translatedFormat('j M') : 'geen leverdatum' }}
                                </small>
                            </span>
                            <span class="shrink-0 text-sm font-bold text-white">{{ $eur($order->total()) }}</span>
                        </a>
                    @endforeach
                </div>

                <div class="mt-4">{{ $orders->links() }}</div>
            @endif
        </div>

        <div>
            <h2 class="mb-3 text-sm font-bold text-white">Inkoopfacturen</h2>

            @if ($invoices->isEmpty())
                <x-empty-state icon="receipt" category="offerte" title="Nog geen inkoopfacturen"
                               subtitle="Na goedkeuring tellen ze mee als werkelijke kosten in de nacalculatie." />
            @else
                <div class="overflow-hidden rounded-2xl border border-surface-700 bg-surface-800">
                    @foreach ($invoices as $invoice)
                        <div class="border-b border-surface-700 p-4 last:border-b-0">
                            <div class="flex items-center gap-3">
                                <span class="min-w-0 flex-1">
                                    <span class="flex flex-wrap items-center gap-2">
                                        <b class="truncate text-sm font-semibold text-white">{{ $invoice->supplier }}</b>
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $invoice->badgeClasses() }}">{{ $invoice->statusLabel() }}</span>
                                    </span>
                                    <small class="mt-0.5 block truncate text-xs text-slate-400">
                                        {{ $invoice->number }}, {{ $invoice->categoryLabel() }},
                                        {{ $invoice->project?->name ?? 'geen project' }},
                                        {{ $invoice->isOverdue() ? 'te laat' : 'vervalt '.$invoice->due_date->translatedFormat('j M') }}
                                    </small>
                                </span>
                                <span class="shrink-0 text-sm font-bold text-white">{{ $eur($invoice->total()) }}</span>
                            </div>

                            @if ($invoice->status === PurchaseInvoice::STATUS_NEW)
                                <div class="mt-2 flex gap-2 border-t border-surface-700 pt-2">
                                    @foreach ([[PurchaseInvoice::STATUS_APPROVED, 'Goedkeuren', true], [PurchaseInvoice::STATUS_DISPUTED, 'Afwijzen', false]] as [$status, $label, $primary])
                                        <form method="POST" action="{{ route('purchase-invoices.update', $invoice) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="{{ $status }}">
                                            <button type="submit" class="rounded-lg px-3 py-1.5 text-xs font-bold transition {{ $primary ? 'bg-brand-500 text-white hover:bg-brand-600' : 'border border-surface-600 text-slate-300 hover:text-white' }}">
                                                {{ $label }}
                                            </button>
                                        </form>
                                    @endforeach
                                </div>
                            @elseif ($invoice->status === PurchaseInvoice::STATUS_APPROVED)
                                <form method="POST" action="{{ route('purchase-invoices.update', $invoice) }}" class="mt-2 border-t border-surface-700 pt-2">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="{{ PurchaseInvoice::STATUS_PAID }}">
                                    <button type="submit" class="rounded-lg border border-surface-600 px-3 py-1.5 text-xs font-semibold text-slate-300 transition hover:text-white">
                                        Betaald melden
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Factuur boeken --}}
            <form method="POST" action="{{ route('purchase-invoices.store') }}" class="mt-4 space-y-3 rounded-2xl border border-surface-700 bg-surface-800 p-5">
                @csrf
                <h3 class="text-sm font-bold text-white">Inkoopfactuur <span class="text-brand-500">boeken</span></h3>

                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field label="Leverancier" name="supplier">
                        <input type="text" name="supplier" id="pi_supplier" required
                               class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                    </x-field>
                    <x-field label="Factuurnummer" name="number">
                        <input type="text" name="number" id="pi_number" required
                               class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                    </x-field>
                    <x-field label="Project" name="project_id">
                        <select name="project_id" id="pi_project"
                                class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                            <option value="">Geen project</option>
                            @foreach ($projects as $project)
                                <option value="{{ $project->id }}">{{ $project->name }}</option>
                            @endforeach
                        </select>
                    </x-field>
                    <x-field label="Kostensoort" name="category">
                        <select name="category" id="pi_category"
                                class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                            @foreach (PurchaseInvoice::CATEGORIES as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-field>
                    <x-field label="Factuurdatum" name="date">
                        <input type="date" name="date" id="pi_date" required value="{{ today()->toDateString() }}"
                               class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                    </x-field>
                    <x-field label="Bedrag excl. btw" name="amount">
                        <input type="number" step="0.01" min="0" name="amount" id="pi_amount" required
                               class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                    </x-field>
                </div>

                <button type="submit" class="w-full rounded-xl bg-brand-500 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600">Boeken</button>
            </form>
        </div>
    </div>
</x-layouts.app>
