{{-- Regel toevoegen: met de hand, of uit de prijslijst zoeken. --}}
<div x-data="{
        zoeken: false,
        term: '',
        resultaten: [],
        bezig: false,
        async zoek() {
            if (this.term.length < 2) { this.resultaten = []; return; }
            this.bezig = true;
            try {
                const res = await fetch('{{ route('price-items.search') }}?q=' + encodeURIComponent(this.term), {
                    headers: { Accept: 'application/json' },
                });
                this.resultaten = (await res.json()).items ?? [];
            } catch (e) { this.resultaten = []; }
            this.bezig = false;
        },
     }" class="border-t border-surface-700 px-3 py-2.5">

    <form method="POST" action="{{ route('budget-lines.store', $paragraph) }}" class="flex flex-wrap items-center gap-2">
        @csrf
        <input type="text" name="description" placeholder="Nieuwe regel"
               class="min-w-0 flex-1 rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">
        <input type="text" name="quantity" value="1" title="Aantal"
               class="w-16 shrink-0 rounded-xl border-surface-600 bg-surface-850 py-2 text-right text-sm text-white focus:border-brand-500 focus:ring-brand-500">
        <input type="text" name="unit" value="st" title="Eenheid"
               class="w-16 shrink-0 rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
        <button type="submit" class="shrink-0 rounded-xl bg-brand-500 px-3.5 py-2 text-sm font-bold text-white transition hover:bg-brand-600">
            <x-icon name="plus" class="h-4 w-4" />
        </button>
        <button type="button" @click="zoeken = !zoeken; if (zoeken) $nextTick(() => $refs.zoekveld.focus())"
                class="shrink-0 rounded-xl border border-surface-600 px-3.5 py-2 text-xs font-semibold text-slate-300 transition hover:text-white">
            Uit de prijslijst
        </button>
    </form>

    <div x-show="zoeken" x-cloak class="mt-2 rounded-xl border border-surface-700 bg-surface-850 p-3">
        <input type="search" x-ref="zoekveld" x-model="term" @input.debounce.300ms="zoek()"
               placeholder="Zoek op naam of artikelnummer"
               class="w-full rounded-xl border-surface-600 bg-surface-800 py-2 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">

        <template x-if="bezig">
            <div class="mt-2"><x-skeleton :lines="2" dark /></div>
        </template>

        <template x-if="!bezig && term.length >= 2 && resultaten.length === 0">
            <p class="mt-2 text-xs text-slate-500">Niets gevonden in de prijslijst.</p>
        </template>

        <div class="mt-2 max-h-56 space-y-1 overflow-y-auto">
            <template x-for="item in resultaten" :key="item.id">
                <form method="POST" action="{{ route('budget-lines.store', $paragraph) }}">
                    @csrf
                    <input type="hidden" name="price_item_id" :value="item.id">
                    <input type="hidden" name="description" :value="item.name">
                    <input type="hidden" name="unit" :value="item.unit">
                    <input type="hidden" name="quantity" value="1">
                    <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left transition hover:bg-white/5">
                        <span class="shrink-0 rounded bg-surface-700 px-1.5 py-0.5 font-mono text-[10px] text-slate-400" x-text="item.code"></span>
                        <span class="min-w-0 flex-1 truncate text-sm text-slate-200" x-text="item.name"></span>
                        <span class="shrink-0 text-xs text-slate-400">
                            <span x-text="new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(item.price)"></span>
                            <span class="text-slate-600" x-text="'per ' + item.unit"></span>
                        </span>
                    </button>
                </form>
            </template>
        </div>
    </div>
</div>
