<x-layouts.app :title="$calculation->title">

    <x-page-header :title="$calculation->title" :subtitle="($calculation->customer?->name ?? 'Geen klant').' · aangemaakt '.$calculation->created_at->translatedFormat('j M Y').($calculation->creator ? ' door '.$calculation->creator->name : '')">
        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $calculation->status->badgeClasses() }}">{{ $calculation->status->label() }}</span>
        @if ($calculation->lead)
            <a href="{{ route('leads.show', $calculation->lead) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Naar aanvraag</a>
        @endif
    </x-page-header>

    @if ($calculation->isLocked())
        <div class="mb-4 flex items-center gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">
            <x-icon name="check" class="h-4 w-4" />
            Deze calculatie is definitief. De regels en prijzen liggen vast — heropen de calculatie om iets te wijzigen.
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">

            {{-- Regels --}}
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white">
                <h2 class="border-b border-gray-100 px-5 py-4 text-sm font-bold text-navy-900">Calculatieregels</h2>
                @if ($calculation->lines->isEmpty())
                    <p class="px-5 py-6 text-sm text-gray-400">Nog geen regels — voeg ze hieronder toe of gebruik de AI-invoer bij een nieuwe calculatie.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs font-bold text-gray-500 uppercase">
                                    <th class="px-5 py-2.5">Omschrijving</th>
                                    <th class="px-3 py-2.5 text-right">Aantal</th>
                                    <th class="px-3 py-2.5">Eenheid</th>
                                    <th class="px-3 py-2.5 text-right">Prijs</th>
                                    <th class="px-3 py-2.5 text-right">Opslag</th>
                                    <th class="px-3 py-2.5 text-right">Totaal</th>
                                    @unless ($calculation->isLocked())<th class="px-3 py-2.5"></th>@endunless
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($calculation->lines as $line)
                                    <tr class="border-t border-gray-100">
                                        <td class="px-5 py-2.5">
                                            <span class="block font-medium text-navy-900">{{ $line->description }}</span>
                                            <span class="mt-0.5 flex items-center gap-1.5 text-xs text-gray-400">
                                                <span class="rounded-full px-1.5 py-0.5 text-[10px] font-semibold {{ $line->type->badgeClasses() }}">{{ $line->type->label() }}</span>
                                                @if ($line->price_source) {{ $line->price_source }} ({{ $line->price_edition }}) @endif
                                            </span>
                                        </td>
                                        <td class="px-3 py-2.5 text-right">
                                            @if ($calculation->isLocked())
                                                {{ rtrim(rtrim(number_format((float) $line->quantity, 2, ',', '.'), '0'), ',') }}
                                            @else
                                                <form method="POST" action="{{ route('calculations.lines.update', [$calculation, $line]) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="number" name="quantity" value="{{ (float) $line->quantity }}" min="0" step="0.01"
                                                           onchange="this.form.submit()"
                                                           class="w-20 rounded-lg border-gray-200 py-1 text-right text-sm focus:border-brand-500 focus:ring-brand-500">
                                                </form>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2.5 text-gray-500">{{ $line->unit ?? '—' }}</td>
                                        <td class="px-3 py-2.5 text-right">€ {{ number_format((float) $line->unit_price, 2, ',', '.') }}</td>
                                        <td class="px-3 py-2.5 text-right text-gray-500">{{ (float) $line->surcharge_pct > 0 ? rtrim(rtrim(number_format((float) $line->surcharge_pct, 2, ',', '.'), '0'), ',').'%' : '—' }}</td>
                                        <td class="px-3 py-2.5 text-right font-semibold text-navy-900">€ {{ number_format($line->total(), 2, ',', '.') }}</td>
                                        @unless ($calculation->isLocked())
                                            <td class="px-3 py-2.5">
                                                <form method="POST" action="{{ route('calculations.lines.destroy', [$calculation, $line]) }}" onsubmit="return confirm('Regel verwijderen?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="rounded p-1 text-gray-300 transition hover:text-red-500" title="Verwijderen">
                                                        <x-icon name="trash" class="h-4 w-4" />
                                                    </button>
                                                </form>
                                            </td>
                                        @endunless
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            {{-- Regel toevoegen met prijsbibliotheek-picker --}}
            @unless ($calculation->isLocked())
                <section class="rounded-2xl border border-gray-200 bg-white p-5"
                         x-data="{
                             q: '',
                             results: [],
                             open: false,
                             line: { price_item_id: '', type: 'arbeid', description: '', quantity: 1, unit: '', unit_price: '', surcharge_pct: 0 },
                             async search() {
                                 if (this.q.trim().length < 2) { this.results = []; this.open = false; return; }
                                 const response = await fetch('{{ route('price-items.search') }}?q=' + encodeURIComponent(this.q), { headers: { 'Accept': 'application/json' } });
                                 this.results = (await response.json()).items;
                                 this.open = true;
                             },
                             pick(item) {
                                 this.line = { price_item_id: item.id, type: item.type, description: item.name, quantity: this.line.quantity || 1, unit: item.unit, unit_price: item.price, surcharge_pct: item.surcharge_pct };
                                 this.q = item.name + ' — ' + item.source;
                                 this.open = false;
                             },
                             clearPick() { this.line.price_item_id = ''; },
                         }">
                    <h2 class="mb-3 text-sm font-bold text-navy-900">Regel toevoegen</h2>

                    <div class="relative mb-3">
                        <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <input type="text" x-model="q" @input.debounce.300ms="search()" @focus="q.trim().length >= 2 && (open = true)" @click.outside="open = false"
                               placeholder="Zoek in de prijsbibliotheek… (bijv. stucwerk, tegels, elektra)"
                               class="w-full rounded-xl border-gray-200 bg-gray-50 py-2.5 pl-9 text-sm placeholder:text-gray-400 focus:border-brand-500 focus:bg-white focus:ring-brand-500">
                        <div x-show="open && results.length" x-cloak class="absolute z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-xl border border-gray-200 bg-white shadow-lg">
                            <template x-for="item in results" :key="item.id">
                                <button type="button" @click="pick(item)" class="flex w-full items-center justify-between gap-3 px-4 py-2.5 text-left text-sm hover:bg-brand-50">
                                    <span class="min-w-0">
                                        <span class="block truncate font-medium text-navy-900" x-text="item.name"></span>
                                        <span class="block text-xs text-gray-400"><span x-text="item.type_label"></span> · <span x-text="item.source"></span></span>
                                    </span>
                                    <span class="shrink-0 text-sm font-semibold text-navy-900">€ <span x-text="item.price.toFixed(2).replace('.', ',')"></span><span class="text-xs font-normal text-gray-400"> / <span x-text="item.unit"></span></span></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('calculations.lines.store', $calculation) }}" class="grid gap-3 sm:grid-cols-12">
                        @csrf
                        <input type="hidden" name="price_item_id" :value="line.price_item_id">
                        <div class="sm:col-span-2">
                            <select name="type" x-model="line.type" @change="clearPick()" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                                @foreach (\App\Enums\CalculationLineType::cases() as $type)
                                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-4">
                            <input type="text" name="description" x-model="line.description" required placeholder="Omschrijving"
                                   class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        </div>
                        <div class="sm:col-span-1">
                            <input type="number" name="quantity" x-model="line.quantity" min="0" step="0.01" required placeholder="Aantal"
                                   class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        </div>
                        <div class="sm:col-span-1">
                            <input type="text" name="unit" x-model="line.unit" placeholder="m2" maxlength="20"
                                   class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        </div>
                        <div class="sm:col-span-2">
                            <input type="number" name="unit_price" x-model="line.unit_price" min="0" step="0.01" required placeholder="Prijs €"
                                   class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        </div>
                        <input type="hidden" name="surcharge_pct" :value="line.surcharge_pct">
                        <div class="sm:col-span-2">
                            <button type="submit" class="w-full rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600">+ Toevoegen</button>
                        </div>
                    </form>
                    @error('description')<p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                    @error('unit_price')<p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                </section>
            @endunless

            @if ($calculation->description)
                <section class="rounded-2xl border border-gray-200 bg-white p-5">
                    <h2 class="mb-2 text-sm font-bold text-navy-900">Opname / omschrijving</h2>
                    <p class="text-sm whitespace-pre-line text-gray-600">{{ $calculation->description }}</p>
                </section>
            @endif
        </div>

        <div class="space-y-4">
            {{-- Totalen --}}
            <section class="rounded-2xl bg-navy-950 p-5 text-white">
                <h2 class="mb-3 text-sm font-bold">Totalen</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-navy-300">Subtotaal regels</dt><dd class="font-medium">€ {{ number_format($calculation->subtotal(), 2, ',', '.') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-navy-300">Risico/onvoorzien ({{ rtrim(rtrim(number_format((float) $calculation->risk_pct, 2, ',', '.'), '0'), ',') }}%)</dt><dd class="font-medium">€ {{ number_format($calculation->riskAmount(), 2, ',', '.') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-navy-300">Marge ({{ rtrim(rtrim(number_format((float) $calculation->margin_pct, 2, ',', '.'), '0'), ',') }}%)</dt><dd class="font-medium">€ {{ number_format($calculation->marginAmount(), 2, ',', '.') }}</dd></div>
                    <div class="flex justify-between border-t border-navy-800 pt-2"><dt class="font-semibold">Totaal excl. btw</dt><dd class="font-bold">€ {{ number_format($calculation->totalExcl(), 2, ',', '.') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-navy-300">Btw ({{ rtrim(rtrim(number_format((float) $calculation->vat_pct, 2, ',', '.'), '0'), ',') }}%)</dt><dd class="font-medium">€ {{ number_format($calculation->vatAmount(), 2, ',', '.') }}</dd></div>
                    <div class="flex justify-between border-t border-navy-800 pt-2 text-base"><dt class="font-bold">Totaal incl. btw</dt><dd class="font-bold text-brand-300">€ {{ number_format($calculation->totalIncl(), 2, ',', '.') }}</dd></div>
                </dl>
            </section>

            {{-- Instellingen --}}
            @unless ($calculation->isLocked())
                <section class="rounded-2xl border border-gray-200 bg-white p-5">
                    <h2 class="mb-3 text-sm font-bold text-navy-900">Opslagen & btw</h2>
                    <form method="POST" action="{{ route('calculations.update', $calculation) }}" class="grid grid-cols-3 gap-3">
                        @csrf
                        @method('PATCH')
                        <x-field label="Onvoorzien %" name="risk_pct">
                            <input type="number" name="risk_pct" value="{{ (float) $calculation->risk_pct }}" min="0" max="100" step="0.5" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        </x-field>
                        <x-field label="Marge %" name="margin_pct">
                            <input type="number" name="margin_pct" value="{{ (float) $calculation->margin_pct }}" min="0" max="100" step="0.5" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        </x-field>
                        <x-field label="Btw %" name="vat_pct">
                            <input type="number" name="vat_pct" value="{{ (float) $calculation->vat_pct }}" min="0" max="100" step="0.5" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        </x-field>
                        <div class="col-span-3">
                            <button type="submit" class="rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Opslaan</button>
                        </div>
                    </form>
                    <p class="mt-2 text-xs text-gray-400">Tip: voor stuc- en schilderwerk aan woningen ouder dan 2 jaar geldt 9% btw op arbeid.</p>
                </section>
            @endunless

            {{-- Acties --}}
            <section class="rounded-2xl border border-gray-200 bg-white p-5">
                <h2 class="mb-3 text-sm font-bold text-navy-900">Acties</h2>
                <div class="space-y-2">
                    @if ($calculation->isLocked())
                        <form method="POST" action="{{ route('calculations.update', $calculation) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="concept">
                            <button type="submit" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Heropenen (terug naar concept)</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('calculations.update', $calculation) }}" onsubmit="return confirm('Calculatie definitief maken? De regels en prijzen liggen daarna vast.');">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="definitief">
                            <button type="submit" class="w-full rounded-xl bg-navy-950 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-navy-900">Definitief maken</button>
                        </form>
                        <form method="POST" action="{{ route('calculations.destroy', $calculation) }}" onsubmit="return confirm('Calculatie verwijderen? Dit kan niet ongedaan worden gemaakt.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="w-full rounded-xl border border-red-200 bg-white px-4 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-50">Verwijderen</button>
                        </form>
                    @endif
                </div>
                <p class="mt-3 text-xs text-gray-400">De offerte-editor (volgende sprint) maakt van een definitieve calculatie met één actie een offerte.</p>
            </section>
        </div>
    </div>

</x-layouts.app>
