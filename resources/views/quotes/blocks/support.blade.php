@php
    use App\Support\QuoteText;

    $isEdit = ($mode ?? 'view') === 'edit';
    $d = $data ?? [];
@endphp

<section class="rounded-2xl border border-gray-200 bg-white p-6 lg:p-8">
    @if ($isEdit)
        <div x-rich="block.data.title" @blur="save()" data-ph="Garantie & nazorg" class="mb-1 text-lg font-bold text-navy-950"></div>
        <div x-rich="block.data.intro" @blur="save()" data-ph="Korte inleiding (optioneel)" class="mb-3 text-sm text-gray-500"></div>
        <template x-for="(item, i) in block.data.items" :key="i">
            <div class="group flex items-center gap-3 border-b border-gray-100 py-3">
                <x-icon name="wrench-screwdriver" class="h-4 w-4 shrink-0 text-brand-500" />
                <div x-rich="item.text" @blur="save()" data-ph="Nazorg…" class="min-w-0 flex-1 text-sm font-medium text-navy-900"></div>
                <span class="shrink-0 rounded-full bg-green-100 px-3 py-0.5 text-xs font-semibold whitespace-nowrap text-green-800">Inbegrepen</span>
                <button type="button" @click="block.data.items.splice(i, 1); save()" class="shrink-0 text-gray-300 opacity-0 transition group-hover:opacity-100 hover:text-red-500" title="Verwijderen">
                    <x-icon name="x-mark" class="h-3.5 w-3.5" />
                </button>
            </div>
        </template>
        <button type="button" @click="block.data.items.push({ text: 'Nieuw item', included: true }); save()" class="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-brand-600 hover:text-brand-500">
            <x-icon name="plus" class="h-3.5 w-3.5" /> Item toevoegen
        </button>
    @else
        <h2 class="mb-1 text-lg font-bold text-navy-950">{!! QuoteText::render($d['title'] ?? 'Garantie & nazorg') !!}</h2>
        @if (filled($d['intro'] ?? ''))
            <div class="mb-3 text-sm text-gray-500">{!! QuoteText::render($d['intro']) !!}</div>
        @endif
        @foreach (($d['items'] ?? []) as $item)
            @php $itemText = is_array($item) ? ($item['text'] ?? '') : $item; @endphp
            @continue(blank($itemText))
            <div class="flex items-center justify-between gap-3 border-b border-gray-100 py-3">
                <div class="flex min-w-0 items-start gap-3">
                    <x-icon name="wrench-screwdriver" class="mt-0.5 h-4 w-4 shrink-0 text-brand-500" />
                    <span class="text-sm font-medium text-navy-900">{!! QuoteText::render($itemText) !!}</span>
                </div>
                <span class="shrink-0 rounded-full bg-green-100 px-3 py-0.5 text-xs font-semibold whitespace-nowrap text-green-800">Inbegrepen</span>
            </div>
        @endforeach
    @endif
</section>
