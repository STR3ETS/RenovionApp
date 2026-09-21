{{-- Offerteregels-editor. Verwacht $initialLines (array van regels). --}}
<div x-data="{
        lines: {{ Js::from($initialLines) }},
        add() { this.lines.push({ description: '', quantity: 1, unit: 'm²', unit_price: '', vat_rate: 21 }); },
        remove(index) { this.lines.splice(index, 1); },
        lineTotal(line) { return (parseFloat(line.quantity) || 0) * (parseFloat(line.unit_price) || 0); },
        get subtotal() { return this.lines.reduce((sum, line) => sum + this.lineTotal(line), 0); },
        get vat() { return this.lines.reduce((sum, line) => sum + this.lineTotal(line) * ((parseFloat(line.vat_rate) || 0) / 100), 0); },
        get total() { return this.subtotal + this.vat; },
        fmt(value) { return '€ ' + value.toLocaleString('nl-NL', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
    }">

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
            <button type="button" @click="remove(index)" x-show="lines.length > 1"
                    class="col-span-2 inline-flex items-center justify-end gap-1 text-right text-gray-400 transition hover:text-red-500 sm:col-span-12 sm:-mt-1 sm:text-xs"><x-icon name="x-mark" class="h-3 w-3" /> verwijderen</button>
        </div>
    </template>

    <button type="button" @click="add()"
            class="mt-1 rounded-lg border border-dashed border-gray-400 px-4 py-2 text-sm font-semibold text-gray-600 transition hover:border-brand-500 hover:text-brand-600">
        + Regel toevoegen
    </button>

    <dl class="mt-5 ml-auto w-full max-w-xs space-y-1 text-sm">
        <div class="flex justify-between"><dt class="text-gray-500">Subtotaal</dt><dd class="font-semibold" x-text="fmt(subtotal)"></dd></div>
        <div class="flex justify-between"><dt class="text-gray-500">BTW</dt><dd class="font-semibold" x-text="fmt(vat)"></dd></div>
        <div class="flex justify-between border-t border-gray-200 pt-1 text-base"><dt class="font-bold text-navy-900">Totaal</dt><dd class="font-bold text-navy-900" x-text="fmt(total)"></dd></div>
    </dl>
</div>
