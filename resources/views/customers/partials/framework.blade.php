@php
    use App\Support\Margin;
    $eur = fn (float $v) => '€ '.number_format($v, 2, ',', '.');
@endphp

{{-- Raamovereenkomst: looptijd, vaste afspraken en vaste verkoopprijzen --}}
<section class="mb-4 rounded-2xl border border-surface-700 bg-surface-800">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-surface-700 px-5 py-4">
        <div>
            <h2 class="text-sm font-bold text-ink">Raam<span class="text-brand-500">overeenkomst</span></h2>
            <p class="mt-0.5 text-xs text-faint">
                Vaste verkoopprijzen per artikel. Onder {{ Margin::format(Margin::baseline()) }} marge kleurt een regel oranje, onder {{ Margin::format(Margin::floor()) }} rood.
            </p>
        </div>
    </div>

    @forelse ($customer->frameworkAgreements as $agreement)
        @php($days = $agreement->daysLeft())
        <div class="border-b border-surface-700 p-5 last:border-b-0" x-data="{ edit: false }">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="flex flex-wrap items-center gap-2 text-sm font-bold text-ink">
                        {{ $agreement->number }}
                        @if ($agreement->isRunning())
                            <span class="rounded-full bg-green-500/15 px-2 py-0.5 text-[10px] font-bold text-green-300">Loopt</span>
                        @else
                            <span class="rounded-full bg-ink/10 px-2 py-0.5 text-[10px] font-bold text-muted">Niet actief</span>
                        @endif
                        @if ($agreement->preset_active)
                            <span class="rounded-full bg-brand-500/15 px-2 py-0.5 text-[10px] font-bold text-brand-300">Calculatie-preset aan</span>
                        @endif
                    </p>
                    <p class="mt-1 text-xs text-muted">
                        {{ $agreement->starts_on->translatedFormat('j M Y') }}
                        {{ $agreement->ends_on ? 't/m '.$agreement->ends_on->translatedFormat('j M Y') : 'zonder einddatum' }},
                        betalen binnen {{ $agreement->payment_days }} dagen,
                        opslag {{ rtrim(rtrim(number_format((float) $agreement->overhead_pct, 2, ',', '.'), '0'), ',') }}% en {{ rtrim(rtrim(number_format((float) $agreement->profit_pct, 2, ',', '.'), '0'), ',') }}%
                    </p>
                    @if ($days !== null && $days >= 0 && $days <= 31)
                        <p class="mt-1 text-xs font-semibold text-amber-300">Loopt over {{ $days }} {{ $days === 1 ? 'dag' : 'dagen' }} af. Leg een nieuwe periode vast.</p>
                    @endif
                </div>
                <button type="button" @click="edit = !edit" class="text-xs font-semibold text-brand-400 hover:text-brand-300" x-text="edit ? 'Sluiten' : 'Bewerken'"></button>
            </div>

            <form x-show="edit" x-cloak method="POST" action="{{ route('framework-agreements.update', $agreement) }}" class="mt-4 grid gap-3 rounded-xl bg-surface-850 p-4 sm:grid-cols-5">
                @csrf
                @method('PATCH')
                <label class="text-xs text-muted">Start
                    <input type="date" name="starts_on" value="{{ $agreement->starts_on->toDateString() }}" required class="mt-1 w-full rounded-lg border-surface-600 text-sm">
                </label>
                <label class="text-xs text-muted">Einde
                    <input type="date" name="ends_on" value="{{ $agreement->ends_on?->toDateString() }}" class="mt-1 w-full rounded-lg border-surface-600 text-sm">
                </label>
                <label class="text-xs text-muted">Betaaltermijn
                    <input type="number" name="payment_days" value="{{ $agreement->payment_days }}" min="0" max="120" required class="mt-1 w-full rounded-lg border-surface-600 text-sm">
                </label>
                <label class="text-xs text-muted">Algemene kosten %
                    <input type="number" step="0.01" name="overhead_pct" value="{{ (float) $agreement->overhead_pct }}" min="0" class="mt-1 w-full rounded-lg border-surface-600 text-sm">
                </label>
                <label class="text-xs text-muted">Winst en risico %
                    <input type="number" step="0.01" name="profit_pct" value="{{ (float) $agreement->profit_pct }}" min="0" class="mt-1 w-full rounded-lg border-surface-600 text-sm">
                </label>
                <label class="flex items-center gap-2 text-sm text-ink2 sm:col-span-3">
                    <input type="checkbox" name="preset_active" value="1" @checked($agreement->preset_active) class="rounded">
                    Nieuwe begroting voor deze klant start met deze vaste prijzen
                </label>
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <button type="submit" class="btn pri sm">Opslaan</button>
                </div>
            </form>

            {{-- Vaste afspraken --}}
            <div class="mt-4">
                <p class="mb-2 text-[10.5px] font-semibold tracking-[0.1em] text-faint uppercase">Vaste afspraken</p>
                <ul class="space-y-1.5">
                    @forelse ($agreement->terms as $term)
                        <li class="flex items-start justify-between gap-3 text-sm text-ink2">
                            <span>{{ $term->body }}</span>
                            <form method="POST" action="{{ route('framework-terms.destroy', $term) }}" class="shrink-0">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-faint hover:text-red-300" aria-label="Afspraak verwijderen">Verwijderen</button>
                            </form>
                        </li>
                    @empty
                        <li class="text-sm text-faint">Nog geen afspraken vastgelegd.</li>
                    @endforelse
                </ul>
                <form method="POST" action="{{ route('framework-terms.store', $agreement) }}" class="mt-2 flex gap-2">
                    @csrf
                    <input type="text" name="body" required placeholder="Afspraak, bijvoorbeeld: opdrachten binnen vijf werkdagen ingepland"
                           class="flex-1 rounded-full border-surface-600 text-sm">
                    <button type="submit" class="btn sm">Toevoegen</button>
                </form>
            </div>

            {{-- Vaste prijzen met marge --}}
            <div class="mt-5">
                <p class="mb-2 text-[10.5px] font-semibold tracking-[0.1em] text-faint uppercase">Vaste prijzen</p>

                @if ($agreement->prices->isNotEmpty())
                    <div class="tw overflow-hidden rounded-xl border border-surface-700">
                        <table class="t">
                            <thead>
                                <tr>
                                    <th>Artikel</th>
                                    <th>Eenheid</th>
                                    <th class="r">Kostprijs</th>
                                    <th class="r">Vaste prijs</th>
                                    <th class="r">Marge</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($agreement->prices as $price)
                                    @php($margin = $price->marginPct())
                                    <tr>
                                        <td>
                                            <b>{{ $price->description }}</b>
                                            @if ($price->priceItem)
                                                <span class="sub">{{ $price->priceItem->code }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $price->unit }}</td>
                                        <td class="r">{{ $price->costPrice() === null ? 'onbekend' : $eur($price->costPrice()) }}</td>
                                        <td class="r"><b>{{ $eur((float) $price->fixed_price) }}</b></td>
                                        <td class="r">
                                            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ Margin::badgeClasses($margin) }}">{{ Margin::format($margin) }}</span>
                                        </td>
                                        <td class="r">
                                            <form method="POST" action="{{ route('framework-prices.destroy', $price) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-xs text-faint hover:text-red-300">Verwijderen</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-faint">Nog geen vaste prijzen. Voeg ze hieronder toe uit de prijslijst.</p>
                @endif

                <form method="POST" action="{{ route('framework-prices.store', $agreement) }}" class="mt-3 grid gap-2 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_auto]">
                    @csrf
                    <select name="price_item_id" required class="rounded-full border-surface-600 text-sm" aria-label="Artikel uit de prijslijst">
                        <option value="">Artikel uit de prijslijst</option>
                        @foreach ($priceItems as $item)
                            <option value="{{ $item->id }}">{{ $item->code }}, {{ $item->name }} ({{ $eur((float) $item->unit_price) }} per {{ $item->unit }})</option>
                        @endforeach
                    </select>
                    <input type="text" inputmode="decimal" name="fixed_price" required placeholder="Vaste prijs, bijvoorbeeld 32,50"
                           class="rounded-full border-surface-600 text-sm" aria-label="Vaste prijs">
                    <button type="submit" class="btn pri sm">Toevoegen</button>
                </form>
            </div>

            <form method="POST" action="{{ route('framework-agreements.destroy', $agreement) }}" class="mt-4"
                  onsubmit="return confirm('Deze raamovereenkomst met alle vaste prijzen verwijderen?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-xs text-faint hover:text-red-300">Overeenkomst verwijderen</button>
            </form>
        </div>
    @empty
        <div class="p-5">
            <p class="text-sm text-muted">Nog geen raamovereenkomst met deze klant.</p>
        </div>
    @endforelse

    {{-- Nieuwe overeenkomst of een nieuwe periode vastleggen --}}
    <form method="POST" action="{{ route('framework-agreements.store', $customer) }}" class="grid gap-3 border-t border-surface-700 bg-surface-850 p-5 sm:grid-cols-4">
        @csrf
        <label class="text-xs text-muted">Start
            <input type="date" name="starts_on" value="{{ today()->toDateString() }}" required class="mt-1 w-full rounded-lg border-surface-600 text-sm">
        </label>
        <label class="text-xs text-muted">Einde
            <input type="date" name="ends_on" value="{{ today()->addYear()->toDateString() }}" class="mt-1 w-full rounded-lg border-surface-600 text-sm">
        </label>
        <label class="text-xs text-muted">Betaaltermijn in dagen
            <input type="number" name="payment_days" value="30" min="0" max="120" required class="mt-1 w-full rounded-lg border-surface-600 text-sm">
        </label>
        <div class="flex items-end">
            <button type="submit" class="w-full rounded-full border border-surface-600 py-2 text-sm font-semibold text-ink transition hover:border-brand-500 hover:text-ink">
                {{ $customer->frameworkAgreements->isEmpty() ? 'Raamovereenkomst vastleggen' : 'Nieuwe periode vastleggen' }}
            </button>
        </div>
    </form>
</section>
