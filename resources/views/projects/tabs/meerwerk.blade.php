@php
    use App\Models\ExtraWork;
    use App\Support\InvoicePlan;
    $eur = fn (float $v) => '€ '.number_format($v, 2, ',', '.');
    $num = fn (float $v) => rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
    $canInvoice = auth()->user()->can('module', 'facturatie');
    $akkoord = $extras->filter->isApproved();
@endphp

<div class="space-y-4">
    <x-kpi-row>
        <x-kpi dark label="Meerwerk met akkoord" :value="$eur((float) $akkoord->sum('amount'))" :sub="$akkoord->count().' '.($akkoord->count() === 1 ? 'opdracht' : 'opdrachten').', apart gefactureerd'" />
        <x-kpi label="Wacht op de klant" :value="(string) $extras->filter->awaitsCustomer()->count()" :alert="$extras->filter->awaitsCustomer()->isNotEmpty()"
               :sub="$eur((float) $extras->filter->awaitsCustomer()->sum('amount'))" />
        <x-kpi label="Opdracht volgens plan" :value="$eur($plan->base)" :sub="$plan->source ? 'uit '.$plan->source.' '.$plan->reference : 'nog geen plan'" />
        <x-kpi label="Al gefactureerd uit het plan" :value="$eur($plan->invoicedAmount())" sub="termijnen met een factuur" />
    </x-kpi-row>

    {{-- Meerwerk als eigen opdracht --}}
    <section class="rounded-2xl border border-surface-700 bg-surface-800">
        <div class="border-b border-surface-700 px-5 py-4">
            <h2 class="text-sm font-bold text-ink">Meer<span class="text-brand-500">werk</span></h2>
            <p class="mt-0.5 text-xs text-faint">
                Elk meerwerk is een eigen opdracht onder {{ $project->number() }}. Pas uitvoeren en factureren na akkoord van de klant.
                De factuur staat los van de termijnen, dus de hoofdfactuur wacht er nooit op.
            </p>
        </div>

        @if ($extras->isEmpty())
            <p class="p-5 text-sm text-faint">Nog geen meerwerk op dit project.</p>
        @else
            <div class="tw">
                <table class="t">
                    <thead>
                        <tr>
                            <th>Opdracht</th>
                            <th>Status</th>
                            <th class="r">Bedrag excl. btw</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($extras as $extra)
                            <tr>
                                <td>
                                    <b>{{ $extra->number }}</b>
                                    <span class="sub">{{ $extra->description }}</span>
                                    @if ($extra->details)
                                        <span class="sub whitespace-pre-line">{{ $extra->details }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $extra->badgeClasses() }}">{{ $extra->statusLabel() }}</span>
                                    @if ($extra->approved_at)
                                        <span class="sub">{{ $extra->approved_name }}, {{ $extra->approved_at->translatedFormat('j M Y') }}</span>
                                    @elseif ($extra->sent_at)
                                        <span class="sub">naar de klant op {{ $extra->sent_at->translatedFormat('j M Y') }}</span>
                                    @endif
                                    @if ($extra->invoice)
                                        <a href="{{ route('invoices.show', $extra->invoice) }}" class="sub hover:text-ink">Factuur {{ $extra->invoice->number }}</a>
                                    @endif
                                </td>
                                <td class="r"><b>{{ $eur((float) $extra->amount) }}</b></td>
                                <td class="r">
                                    <div class="flex flex-wrap justify-end gap-1.5">
                                        @if ($extra->status === ExtraWork::STATUS_DRAFT)
                                            <form method="POST" action="{{ route('extra-works.send', $extra) }}">
                                                @csrf
                                                <button type="submit" class="btn pri sm">Naar de klant</button>
                                            </form>
                                            <form method="POST" action="{{ route('extra-works.destroy', $extra) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn sm">Verwijderen</button>
                                            </form>
                                        @endif

                                        @if ($extra->awaitsCustomer())
                                            <span x-data="{ copied: false }">
                                                <button type="button"
                                                        @click="navigator.clipboard.writeText(@js($extra->publicUrl())).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                                                        class="btn sm">
                                                    <span x-show="!copied">Link kopiëren</span>
                                                    <span x-show="copied" x-cloak>Gekopieerd</span>
                                                </button>
                                            </span>
                                            <form method="POST" action="{{ route('extra-works.approve', $extra) }}" class="flex gap-1.5"
                                                  onsubmit="return confirm('Akkoord vastleggen dat de klant buiten het systeem om gaf?')">
                                                @csrf
                                                <input type="text" name="approved_name" required maxlength="200" placeholder="Wie gaf akkoord" aria-label="Wie gaf akkoord"
                                                       class="w-36 rounded-full border-surface-600 py-1.5 text-xs">
                                                <button type="submit" class="btn sm">Akkoord vastleggen</button>
                                            </form>
                                        @endif

                                        @if ($extra->status === ExtraWork::STATUS_APPROVED && $canInvoice)
                                            <form method="POST" action="{{ route('extra-works.invoice', $extra) }}">
                                                @csrf
                                                <button type="submit" class="btn pri sm">Factureren</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <form method="POST" action="{{ route('extra-works.store', $project) }}" class="grid gap-3 border-t border-surface-700 p-5 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_auto] sm:items-end">
            @csrf
            <label class="block text-xs text-muted">Wat is het meerwerk
                <input type="text" name="description" value="{{ old('description') }}" required maxlength="255" placeholder="Bijvoorbeeld extra wandcontactdozen keuken"
                       class="mt-1 w-full rounded-xl border-surface-600 text-sm">
            </label>
            <label class="block text-xs text-muted">Bedrag excl. btw
                <input type="text" inputmode="decimal" name="amount" value="{{ old('amount') }}" required placeholder="1.250,00" class="mt-1 w-full rounded-xl border-surface-600 text-sm">
            </label>
            <button type="submit" class="btn pri">Meerwerk opstellen</button>
            <label class="block text-xs text-muted sm:col-span-3">Toelichting voor de klant
                <textarea name="details" rows="2" maxlength="5000" class="mt-1 w-full rounded-xl border-surface-600 text-sm">{{ old('details') }}</textarea>
            </label>
            @error('amount')<p class="text-xs font-medium text-red-400 sm:col-span-3">{{ $message }}</p>@enderror
        </form>
    </section>

    {{-- Factuurplan --}}
    <section class="rounded-2xl border border-surface-700 bg-surface-800">
        <div class="border-b border-surface-700 px-5 py-4">
            <h2 class="text-sm font-bold text-ink">Factuur<span class="text-brand-500">plan</span></h2>
            <p class="mt-0.5 text-xs text-faint">
                @if ($plan->source === InvoicePlan::SOURCE_BUDGET)
                    De termijnen uit begroting {{ $plan->reference }}. Het bedrag van elke factuur komt uit de termijn, zodat opdracht en facturen nooit uit elkaar lopen.
                @elseif ($plan->source === InvoicePlan::SOURCE_QUOTE)
                    Uit de getekende offerte {{ $plan->reference }}, met het standaard betaalschema. Leg de termijnen vast in een begroting om ze te kunnen factureren.
                @else
                    Zodra de offerte is getekend of er een begroting met termijnen is, staat hier wanneer we wat factureren.
                @endif
            </p>
        </div>

        @if ($plan->isEmpty())
            <p class="p-5 text-sm text-faint">Nog geen factuurplan voor dit project.</p>
        @else
            <div class="tw">
                <table class="t">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Termijn</th>
                            <th class="r">%</th>
                            <th class="r">Excl. btw</th>
                            <th>Factuur</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($plan->rows as $row)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td><b>{{ $row['description'] }}</b></td>
                                <td class="r">{{ $row['percentage'] > 0 ? $num($row['percentage']) : 'vast' }}</td>
                                <td class="r">{{ $eur($row['amount']) }}</td>
                                <td>
                                    @if ($row['invoice'])
                                        <a href="{{ route('invoices.show', $row['invoice']) }}" class="hover:text-ink">{{ $row['invoice']->number }}</a>
                                        <span class="ml-1 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $row['invoice']->status->badgeClasses() }}">{{ $row['invoice']->status->label() }}</span>
                                    @else
                                        <span class="text-faint">nog niet gefactureerd</span>
                                    @endif
                                </td>
                                <td class="r">
                                    @if ($row['term'] && ! $row['invoice'] && $plan->invoiceable && $canInvoice)
                                        <form method="POST" action="{{ route('invoices.term', $row['term']) }}">
                                            @csrf
                                            <button type="submit" class="btn sm">Factuur maken</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2"><b>Totaal</b></td>
                            <td class="r">{{ $num($plan->coverage()) }}</td>
                            <td class="r"><b>{{ $eur((float) $plan->rows->sum('amount')) }}</b></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            @if ($plan->coverage() > 0 && abs($plan->coverage() - 100) > 0.01)
                <p class="border-t border-surface-700 px-5 py-3 text-sm font-semibold text-amber-300">
                    De termijnen tellen op tot {{ $num($plan->coverage()) }} procent. Er mist geld in het plan, of er zit te veel in.
                </p>
            @endif
        @endif
    </section>
</div>
