{{--
    Offerteregels-editor. Verwacht $initialLines (array van regels) en optioneel
    $proposeUrl: dan kan Nova op basis van een omschrijving zelf de passende
    artikelen uit de prijslijst voorstellen, die je hier eerst controleert.
--}}
@php $proposeUrl = $proposeUrl ?? null; @endphp

<div x-data="{
        lines: {{ Js::from(collect($initialLines)->map(fn ($line) => [...$line, 'is_estimate' => (bool) ($line['is_estimate'] ?? false)])->values()->all()) }},
        add() { this.lines.push({ description: '', quantity: 1, unit: 'm²', unit_price: '', vat_rate: 21, is_estimate: false }); },
        remove(index) { this.lines.splice(index, 1); },
        lineTotal(line) { return (parseFloat(line.quantity) || 0) * (parseFloat(line.unit_price) || 0); },
        get subtotal() { return this.lines.reduce((sum, line) => sum + this.lineTotal(line), 0); },
        get vat() { return this.lines.reduce((sum, line) => sum + this.lineTotal(line) * ((parseFloat(line.vat_rate) || 0) / 100), 0); },
        get total() { return this.subtotal + this.vat; },
        fmt(value) { return '€ ' + value.toLocaleString('nl-NL', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },

        // Regels toevoegen zonder lege invoerregels te laten staan.
        append(newLines) {
            this.lines = this.lines.filter(line => (line.description || '').trim() !== '' || parseFloat(line.unit_price) > 0);
            this.lines.push(...newLines);
            if (this.lines.length === 0) this.add();
        },

        // Prijslijst-picker
        picker: false,
        pickerQ: '',
        pickerItems: [],
        pickerLoading: false,
        async zoek() {
            this.pickerLoading = true;
            try {
                const response = await fetch('{{ route('price-items.search') }}?q=' + encodeURIComponent(this.pickerQ), { headers: { 'Accept': 'application/json' } });
                this.pickerItems = (await response.json()).items;
            } catch { this.pickerItems = []; }
            this.pickerLoading = false;
        },
        kies(item) {
            const units = { 'm2': 'm²', 'm1': 'm¹', 'uur': 'uur', 'stuk': 'stuks', 'stuks': 'stuks', 'post': 'post', 'm²': 'm²', 'm¹': 'm¹' };
            this.append([{
                description: item.name,
                quantity: 1,
                unit: units[item.unit] || 'post',
                unit_price: Math.round(item.price * (1 + (item.surcharge_pct || 0) / 100) * 100) / 100,
                vat_rate: 21,
                is_estimate: item.type === 'stelpost',
            }]);
            this.picker = false;
            this.pickerQ = '';
            this.pickerItems = [];
        },

        // Nova-voorstel op basis van een omschrijving
        nova: { open: false, prompt: '', loading: false, error: null, note: null },
        async stelVoor() {
            if (this.nova.loading || !this.nova.prompt.trim()) return;
            this.nova.loading = true;
            this.nova.error = null;
            this.nova.note = null;
            try {
                const data = await postJson(@js($proposeUrl), { description: this.nova.prompt });
                if (data.ok) {
                    this.append(data.lines);
                    this.nova.note = data.note;
                    this.nova.prompt = '';
                } else {
                    this.nova.error = data.error || 'Er ging iets mis.';
                }
            } catch (e) { this.nova.error = (e && e.message) || 'Er ging iets mis.'; }
            this.nova.loading = false;
        },
    }">

    @if ($proposeUrl)
        {{-- Nova zoekt zelf de artikelen uit de prijslijst --}}
        <div class="mb-4 rounded-2xl border border-calc-200 bg-calc-50 p-4">
            <div class="flex items-center gap-3">
                <img src="{{ asset('nova.jpg') }}" alt="Nova" class="h-9 w-9 rounded-full object-cover ring-2 ring-brand-500">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-navy-900">Nova stelt de posten voor</p>
                    <p class="text-xs text-gray-500">Beschrijf wat er moet gebeuren; Nova zoekt de passende artikelen in de prijslijst. Jij controleert de regels hieronder.</p>
                </div>
            </div>
            <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                <textarea x-model="nova.prompt" rows="2" @keydown.enter.meta.prevent="stelVoor()"
                          placeholder="Bijv. 'Badkamer 6 m2 strippen, wanden en vloer tegelen, wandcloset en meubel plaatsen, sanitair als stelpost.'"
                          class="min-w-0 flex-1 resize-none rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
                <button type="button" @click="stelVoor()" :disabled="nova.loading || !nova.prompt.trim()"
                        class="shrink-0 rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600 disabled:opacity-50">
                    <span x-text="nova.loading ? 'Nova zoekt…' : 'Stel posten voor'"></span>
                </button>
            </div>
            <div x-show="nova.loading" x-cloak class="mt-3"><x-skeleton :lines="2" /></div>
            <p x-show="nova.error" x-text="nova.error" x-cloak class="mt-2 text-xs font-medium text-red-600"></p>
            <p x-show="nova.note" x-cloak class="mt-2 text-xs text-gray-600"><span class="font-semibold text-navy-900">Aannames van Nova:</span> <span x-text="nova.note"></span></p>
        </div>
    @endif

    <div class="mb-2 hidden grid-cols-12 gap-2 px-1 text-xs font-semibold text-gray-500 sm:grid">
        <span class="col-span-4">Werkzaamheid</span>
        <span class="col-span-2">Hoeveelheid</span>
        <span class="col-span-2">Eenheid</span>
        <span class="col-span-2">Prijs p/e</span>
        <span class="col-span-1">BTW</span>
        <span class="col-span-1 text-right">Totaal</span>
    </div>

    <template x-for="(line, index) in lines" :key="index">
        <div class="mb-3 grid grid-cols-12 items-center gap-2 rounded-xl border border-gray-200 p-2 sm:mb-2 sm:border-0 sm:p-0">
            <input type="text" :name="`lines[${index}][description]`" x-model="line.description" required placeholder="Bijv. stucwerk wanden"
                   class="col-span-12 rounded-lg border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500 sm:col-span-4">
            <input type="number" :name="`lines[${index}][quantity]`" x-model="line.quantity" required min="0.01" step="0.01"
                   class="col-span-4 rounded-lg border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500 sm:col-span-2">
            <select :name="`lines[${index}][unit]`" x-model="line.unit"
                    class="col-span-4 rounded-lg border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500 sm:col-span-2">
                <option value="m²">m²</option>
                <option value="m¹">m¹</option>
                <option value="uur">uur</option>
                <option value="stuks">stuks</option>
                <option value="post">post</option>
            </select>
            <input type="number" :name="`lines[${index}][unit_price]`" x-model="line.unit_price" required min="0" step="0.01" placeholder="0,00"
                   class="col-span-4 rounded-lg border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500 sm:col-span-2">
            <select :name="`lines[${index}][vat_rate]`" x-model="line.vat_rate"
                    class="col-span-4 rounded-lg border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500 sm:col-span-1">
                <option value="21">21%</option>
                <option value="9">9%</option>
                <option value="0">0%</option>
            </select>
            <span class="col-span-6 text-right text-sm font-semibold text-navy-900 sm:col-span-1" x-text="fmt(lineTotal(line))"></span>
            <input type="hidden" :name="`lines[${index}][is_estimate]`" :value="line.is_estimate ? 1 : 0">
            <div class="col-span-12 flex items-center justify-between gap-2 sm:-mt-1">
                <label class="flex cursor-pointer items-center gap-1.5 text-xs font-medium text-gray-500">
                    <input type="checkbox" x-model="line.is_estimate" class="rounded border-gray-300 text-amber-500 focus:ring-amber-400">
                    stelpost <span class="text-gray-400">(wordt achteraf verrekend)</span>
                </label>
                <button type="button" @click="remove(index)" x-show="lines.length > 1"
                        class="inline-flex items-center gap-1 text-xs text-gray-400 transition hover:text-red-500"><x-icon name="x-mark" class="h-3 w-3" /> verwijderen</button>
            </div>
        </div>
    </template>

    <div class="relative mt-1 flex flex-wrap gap-2">
        <button type="button" @click="add()"
                class="rounded-lg border border-dashed border-gray-400 px-4 py-2 text-sm font-semibold text-gray-600 transition hover:border-brand-500 hover:text-brand-600">
            + Regel toevoegen
        </button>
        <button type="button" @click="picker = !picker; if (picker) { zoek(); $nextTick(() => $refs.pickerInput.focus()); }"
                class="rounded-lg border border-dashed border-calc-500/50 px-4 py-2 text-sm font-semibold text-calc-600 transition hover:border-calc-500 hover:bg-calc-50">
            + Uit de prijslijst
        </button>

        {{-- Prijslijst-picker --}}
        <div x-show="picker" x-cloak @click.outside="picker = false"
             class="absolute bottom-full left-0 z-20 mb-2 w-full max-w-md rounded-2xl border border-gray-200 bg-white p-3 shadow-lg">
            <input type="text" x-ref="pickerInput" x-model="pickerQ" @input.debounce.300ms="zoek()" placeholder="Zoek op naam of artikelnummer…"
                   class="mb-2 w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
            <div class="max-h-60 space-y-1 overflow-y-auto">
                <template x-for="item in pickerItems" :key="item.id">
                    <button type="button" @click="kies(item)"
                            class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left transition hover:bg-calc-50">
                        <span class="w-16 shrink-0 rounded bg-calc-100 py-0.5 text-center font-mono text-[10px] font-bold text-calc-700" x-text="item.code"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium text-navy-900" x-text="item.name"></span>
                            <span class="block text-xs text-gray-400" x-text="item.type_label + ', per ' + item.unit"></span>
                        </span>
                        <span class="shrink-0 text-sm font-semibold whitespace-nowrap text-navy-900" x-text="fmt(item.price)"></span>
                    </button>
                </template>
                <p x-show="!pickerLoading && pickerItems.length === 0" class="px-2 py-3 text-center text-xs text-gray-400">Niets gevonden in de prijslijst.</p>
                <p x-show="pickerLoading" x-cloak class="px-2 py-3 text-center text-xs text-gray-400">Zoeken…</p>
            </div>
        </div>
    </div>

    <dl class="mt-5 ml-auto w-full max-w-xs space-y-1 text-sm">
        <div class="flex justify-between"><dt class="text-gray-500">Subtotaal</dt><dd class="font-semibold" x-text="fmt(subtotal)"></dd></div>
        <div class="flex justify-between"><dt class="text-gray-500">BTW</dt><dd class="font-semibold" x-text="fmt(vat)"></dd></div>
        <div class="flex justify-between border-t border-gray-200 pt-1 text-base"><dt class="font-bold text-navy-900">Totaal</dt><dd class="font-bold text-navy-900" x-text="fmt(total)"></dd></div>
    </dl>
</div>
