@php
    use App\Models\Rfq;
    use App\Support\Margin;
    $eur = fn (float $v) => '€ '.number_format($v, 0, ',', '.');
@endphp

<x-layouts.app :title="$rfq->title">
    <div class="mb-4">
        <a href="{{ route('rfqs.index') }}" class="flex items-center gap-1 text-sm font-semibold text-muted transition hover:text-ink">
            <x-icon name="chevron-left" class="h-4 w-4" /> Prijsaanvragen
        </a>
    </div>

    <x-page-header :title="$rfq->title" :eyebrow="$rfq->number" category="klant"
                   :subtitle="collect([$rfq->project->name, $rfq->chapter_code ? 'hoofdstuk '.$rfq->chapter_code : null, 'begroot '.$eur((float) $rfq->budget)])->filter()->join(', ')">
        <span class="rounded-full px-3 py-1.5 text-xs font-bold {{ $rfq->badgeClasses() }}">{{ $rfq->statusLabel() }}</span>
    </x-page-header>

    @if ($rfq->winner)
        <div class="mb-4 flex flex-wrap items-center gap-3 rounded-2xl border border-green-500/25 bg-green-500/10 p-4">
            <x-icon name="check" class="h-6 w-6 shrink-0 text-green-300" />
            <div class="min-w-0 flex-1">
                <p class="text-sm font-bold text-ink">Gegund aan {{ $rfq->winner->name }}</p>
                <p class="mt-0.5 text-sm text-ink2">
                    {{ $eur((float) ($rfq->winningBid()?->price ?? 0)) }} tegenover {{ $eur((float) $rfq->budget) }} begroot,
                    {{ ($rfq->savings() ?? 0) >= 0 ? 'dus' : 'dus' }}
                    <b class="{{ ($rfq->savings() ?? 0) >= 0 ? 'text-green-300' : 'text-red-300' }}">
                        {{ $eur(abs($rfq->savings() ?? 0)) }} {{ ($rfq->savings() ?? 0) >= 0 ? 'onder' : 'boven' }}
                    </b>
                    de begroting. Opdracht {{ $rfq->order_number }}, {{ $rfq->awarded_on?->translatedFormat('j F Y') }}.
                </p>
            </div>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            {{-- Biedingen vergelijken --}}
            @php
                $open = $rfq->status !== Rfq::STATUS_AWARDED && $rfq->status !== Rfq::STATUS_DONE;
                // Het beste bod bovenaan; de rest blijft zichtbaar, op prijs.
                $bids = $rfq->bids->sortBy(fn ($bid) => [$best?->id === $bid->id ? 0 : 1, (float) $bid->price])->values();
            @endphp
            <div class="rounded-2xl border border-surface-700 bg-surface-800">
                <div class="border-b border-surface-700 px-5 py-4">
                    <h2 class="text-sm font-bold text-ink">Bie<span class="text-brand-500">dingen</span></h2>
                    <p class="mt-0.5 text-xs text-faint">
                        Het beste bod is de hoogste marge bij een complete scope en een goedgekeurd dossier.
                        Onder {{ Margin::format(Margin::baseline()) }} marge waarschuwen we, onder {{ Margin::format(Margin::floor()) }} kan gunnen alleen met een reden en akkoord van de directie.
                    </p>
                </div>

                @if ($bids->isEmpty())
                    <p class="p-5 text-sm text-faint">Nog geen biedingen binnen.</p>
                @else
                    <div class="tw">
                        <table class="t">
                            <thead>
                                <tr>
                                    <th>Partner</th>
                                    <th class="r">Inkoop</th>
                                    <th class="r">Verkoop</th>
                                    <th class="r">Marge</th>
                                    <th class="r">Tegen budget</th>
                                    <th>Start</th>
                                    <th class="r">Compleet</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($bids as $bid)
                                    @php
                                        $afwijking = $bid->varianceAgainstBudget();
                                        $marge = $bid->marginPct();
                                        $verkoop = $bid->saleTotal();
                                    @endphp
                                    <tr class="{{ $best?->id === $bid->id ? 'bg-green-500/5' : '' }}">
                                        <td>
                                            <b>{{ $bid->subcontractor->name }}</b>
                                            @if ($best?->id === $bid->id)
                                                <span class="ml-1 rounded-full bg-green-500/15 px-2 py-0.5 text-[10px] font-bold text-green-300">Beste bod</span>
                                            @endif
                                            @if ($bid->via_whatsapp)
                                                <span class="ml-1 rounded-full bg-ink/10 px-2 py-0.5 text-[10px] font-bold text-ink2">via WhatsApp</span>
                                            @endif
                                            @if ($bid->conditions)
                                                <span class="sub">{{ $bid->conditions }}</span>
                                            @endif
                                            @unless ($bid->subcontractor->mayBeInvited())
                                                <span class="mt-1 inline-block rounded-full bg-red-500/15 px-2 py-0.5 text-[10px] font-bold text-red-300">
                                                    {{ $bid->subcontractor->statusLabel() }}
                                                </span>
                                            @endunless
                                            @if ($bid->margin_approval)
                                                <span class="sub">Onder de bodem gegund door {{ $bid->margin_approval['door'] }}: {{ $bid->margin_approval['reden'] }}</span>
                                            @endif
                                            @if ($bid->purchaseOrder)
                                                <a href="{{ route('purchase-orders.show', $bid->purchaseOrder) }}" class="sub hover:text-ink">Inkooporder {{ $bid->purchaseOrder->number }}, met fotoplicht</a>
                                            @endif
                                        </td>
                                        <td class="r"><b>{{ $eur((float) $bid->price) }}</b></td>
                                        <td class="r">
                                            @if ($verkoop !== null)
                                                {{ $eur($verkoop) }}
                                                @if ($bid->sale_quantity)
                                                    <span class="sub">{{ rtrim(rtrim(number_format((float) $bid->sale_quantity, 2, ',', '.'), '0'), ',') }} {{ $bid->sale_unit }} x {{ $eur((float) $bid->sale_price) }}</span>
                                                @endif
                                            @else
                                                <span class="text-faint">nog niet ingevuld</span>
                                            @endif
                                        </td>
                                        <td class="r">
                                            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ Margin::badgeClasses($marge) }}">{{ Margin::format($marge) }}</span>
                                        </td>
                                        <td class="r">
                                            @if ($afwijking !== null)
                                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $afwijking > 0 ? 'bg-red-500/15 text-red-300' : 'bg-green-500/15 text-green-300' }}">
                                                    {{ $afwijking > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($afwijking, 1, ',', '.'), '0'), ',') }}%
                                                </span>
                                            @else
                                                <span class="text-faint">geen budget</span>
                                            @endif
                                        </td>
                                        <td>
                                            {{ $bid->can_start_on?->translatedFormat('j M') ?? 'onbekend' }}
                                            @if ($bid->days)
                                                <span class="sub">{{ $bid->days }} dagen</span>
                                            @endif
                                        </td>
                                        <td class="r"><span class="{{ $bid->isComplete() ? 'text-green-400' : 'text-amber-300' }}">{{ $bid->completeness }}%</span></td>
                                        <td class="r whitespace-nowrap">
                                            @if ($verkoop !== null)
                                                <form method="POST" action="{{ route('rfq-bids.adopt', [$rfq, $bid]) }}" class="mb-1.5">
                                                    @csrf
                                                    <button type="submit" title="Het verkoopbedrag als post in onze conceptofferte zetten"
                                                            class="btn sm">
                                                        Overnemen in offerte
                                                    </button>
                                                </form>
                                            @endif

                                            @if ($open)
                                                <form method="POST" action="{{ route('rfqs.award', [$rfq, $bid]) }}" class="space-y-1.5">
                                                    @csrf
                                                    @if ($bid->needsDirectorApproval())
                                                        @if ($canApproveLowMargin)
                                                            <input type="text" name="margin_reason" required maxlength="500" placeholder="Reden voor gunnen onder de bodem"
                                                                   class="w-56 rounded-full border-red-500/40 py-1.5 text-xs">
                                                        @else
                                                            <span class="block max-w-[14rem] text-[11px] whitespace-normal text-red-300">Onder de bodem: alleen de directie kan dit bod gunnen.</span>
                                                        @endif
                                                    @endif
                                                    @unless ($bid->needsDirectorApproval() && ! $canApproveLowMargin)
                                                        <button type="submit"
                                                                class="rounded-full px-4 py-1.5 text-xs font-bold transition {{ $best?->id === $bid->id ? 'bg-brand-500 text-white hover:bg-brand-600' : 'border border-surface-600 text-ink hover:text-ink' }}">
                                                            Gunnen
                                                        </button>
                                                    @endunless
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                {{-- Bieding vastleggen --}}
                @if ($rfq->invited->isNotEmpty() && $open)
                    <form method="POST" action="{{ route('rfq-bids.store', $rfq) }}" class="space-y-3 border-t border-surface-700 p-5">
                        @csrf
                        <h3 class="text-sm font-bold text-ink">Bieding <span class="text-brand-500">vastleggen</span></h3>

                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <label class="block text-xs text-muted">Partner
                                <select name="subcontractor_id" required class="mt-1 w-full rounded-xl border-surface-600 text-sm">
                                    @foreach ($rfq->invited as $partner)
                                        <option value="{{ $partner->id }}">{{ $partner->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block text-xs text-muted">Inkoop, wat de partner vraagt
                                <input type="text" inputmode="decimal" name="price" required placeholder="Bijvoorbeeld 4.250,00" class="mt-1 w-full rounded-xl border-surface-600 text-sm">
                            </label>
                            <label class="block text-xs text-muted">Kan starten op
                                <input type="date" name="can_start_on" class="mt-1 w-full rounded-xl border-surface-600 text-sm">
                            </label>
                            <label class="block text-xs text-muted">Compleet in procenten
                                <input type="number" min="0" max="100" name="completeness" value="100" class="mt-1 w-full rounded-xl border-surface-600 text-sm">
                            </label>
                        </div>

                        <div class="grid gap-3 rounded-xl bg-surface-850 p-3 sm:grid-cols-3">
                            <label class="block text-xs text-muted">Verkoop: hoeveelheid
                                <input type="text" inputmode="decimal" name="sale_quantity" value="{{ $lastSale ? (float) $lastSale->sale_quantity ?: '' : '' }}" placeholder="Bijvoorbeeld 120"
                                       class="mt-1 w-full rounded-xl border-surface-600 text-sm">
                            </label>
                            <label class="block text-xs text-muted">Eenheid
                                <input type="text" name="sale_unit" maxlength="20" value="{{ $lastSale?->sale_unit }}" placeholder="m2" class="mt-1 w-full rounded-xl border-surface-600 text-sm">
                            </label>
                            <label class="block text-xs text-muted">Verkoopprijs per eenheid
                                <input type="text" inputmode="decimal" name="sale_price" value="{{ $lastSale ? number_format((float) $lastSale->sale_price, 2, ',', '') : '' }}" placeholder="Bijvoorbeeld 45,00"
                                       class="mt-1 w-full rounded-xl border-surface-600 text-sm">
                            </label>
                            <p class="text-xs text-faint sm:col-span-3">
                                Waarvoor we dit werk zelf verkopen. Daaruit volgt de marge per bod. Zonder hoeveelheid is de prijs het hele bedrag.
                            </p>
                        </div>

                        <input type="text" name="conditions" placeholder="Voorwaarden, bijvoorbeeld: exclusief steiger en afvoer"
                               class="w-full rounded-xl border-surface-600 text-sm">

                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <label class="flex items-center gap-2 text-sm text-ink2">
                                <input type="checkbox" name="via_whatsapp" value="1" class="rounded"> Dit bod kwam binnen via WhatsApp
                            </label>
                            <button type="submit" class="btn pri">Vastleggen</button>
                        </div>
                    </form>
                @endif
            </div>

            @if ($rfq->scope)
                <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                    <h2 class="mb-2 text-sm font-bold text-ink">Werkomschrijving</h2>
                    <p class="text-sm whitespace-pre-line text-ink2">{{ $rfq->scope }}</p>
                </div>
            @endif
        </div>

        <div class="space-y-4">
            {{-- Uitgenodigde partijen --}}
            <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-3 text-sm font-bold text-ink">Uitge<span class="text-brand-500">nodigd</span></h2>

                @if ($rfq->invited->isEmpty())
                    <p class="mb-3 text-sm text-faint">Nog niemand uitgenodigd.</p>
                @else
                    <div class="mb-3 space-y-1.5">
                        @foreach ($rfq->invited as $partner)
                            <div class="flex items-center gap-2 text-sm">
                                <span class="min-w-0 flex-1 truncate text-ink">{{ $partner->name }}</span>
                                @if ($partner->phone && $rfq->status === Rfq::STATUS_SENT)
                                    <a href="https://wa.me/{{ $partner->whatsappNumber() }}?text={{ rawurlencode($rfq->whatsappText()) }}"
                                       target="_blank" rel="noopener" title="Uitvraag via WhatsApp"
                                       class="btn xs shrink-0">WhatsApp</a>
                                @endif
                                @if ($rfq->bids->contains('subcontractor_id', $partner->id))
                                    <span class="shrink-0 rounded-full bg-green-500/15 px-2 py-0.5 text-[10px] font-bold text-green-300">Bood</span>
                                @else
                                    <span class="shrink-0 rounded-full bg-ink/10 px-2 py-0.5 text-[10px] font-bold text-muted">Wacht</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($candidates->isNotEmpty() && $rfq->status !== Rfq::STATUS_AWARDED)
                    <form method="POST" action="{{ route('rfqs.invite', $rfq) }}" class="space-y-2 border-t border-surface-700 pt-3">
                        @csrf
                        <p class="text-xs text-faint">Partijen met een rond dossier kun je uitnodigen.</p>

                        <div class="max-h-56 space-y-1 overflow-y-auto">
                            @foreach ($candidates as $partner)
                                <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 transition hover:bg-ink/5">
                                    <input type="checkbox" name="subcontractor_ids[]" value="{{ $partner->id }}"
                                           class="rounded border-surface-600 bg-surface-750 text-brand-500 focus:ring-brand-500">
                                    <span class="min-w-0 flex-1 truncate text-sm text-ink">{{ $partner->name }}</span>
                                    @unless ($partner->mayBeInvited())
                                        <span class="shrink-0 rounded-full bg-amber-500/15 px-1.5 py-0.5 text-[10px] font-bold text-amber-300" title="{{ $partner->statusLabel() }}">!</span>
                                    @endunless
                                </label>
                            @endforeach
                        </div>

                        <button type="submit" class="btn pri w-full">Uitzetten</button>
                    </form>
                @endif
            </div>

            <form method="POST" action="{{ route('rfqs.update', $rfq) }}" class="space-y-3 rounded-2xl border border-surface-700 bg-surface-800 p-5">
                @csrf
                @method('PATCH')
                <h2 class="text-sm font-bold text-ink">Aanvraag</h2>

                <x-field label="Budget uit de begroting" name="budget">
                    <input type="number" step="0.01" name="budget" value="{{ $rfq->budget }}"
                           class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-ink focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Deadline voor biedingen" name="deadline">
                    <input type="date" name="deadline" value="{{ $rfq->deadline?->toDateString() }}"
                           class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-ink focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Gewenste start" name="starts_on">
                    <input type="date" name="starts_on" value="{{ $rfq->starts_on?->toDateString() }}"
                           class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-ink focus:border-brand-500 focus:ring-brand-500">
                </x-field>
                <x-field label="Werkomschrijving" name="scope">
                    <textarea name="scope" rows="4"
                              class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-ink focus:border-brand-500 focus:ring-brand-500">{{ $rfq->scope }}</textarea>
                </x-field>

                <button type="submit" class="btn pri w-full">Opslaan</button>
            </form>
        </div>
    </div>
</x-layouts.app>
