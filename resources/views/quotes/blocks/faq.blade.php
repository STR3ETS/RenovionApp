@php
    use App\Support\QuoteText;

    $isEdit = ($mode ?? 'view') === 'edit';
    $d = $data ?? [];
@endphp

<section class="rounded-2xl border border-gray-200 bg-white p-6 lg:p-8">
    @if ($isEdit)
        <div x-rich="block.data.title" @blur="save()" data-ph="Veelgestelde vragen" class="mb-4 text-lg font-bold text-navy-950"></div>
        <template x-for="(item, i) in block.data.items" :key="i">
            <div class="group relative mb-3 rounded-xl bg-gray-50 p-5">
                <div x-rich="item.q" @blur="save()" data-ph="Vraag?" class="mb-1.5 text-sm font-bold text-navy-900"></div>
                <div x-rich="item.a" @blur="save()" data-ph="Antwoord…" class="text-sm text-gray-600"></div>
                <button type="button" @click="block.data.items.splice(i, 1); save()" class="absolute top-2 right-2 text-gray-300 opacity-0 transition group-hover:opacity-100 hover:text-red-500" title="Vraag verwijderen">
                    <x-icon name="x-mark" class="h-3.5 w-3.5" />
                </button>
            </div>
        </template>
        <button type="button" @click="block.data.items.push({ q: 'Nieuwe vraag?', a: '' }); save()"
                class="flex w-full items-center justify-center gap-1.5 rounded-xl border border-dashed border-gray-300 p-4 text-xs font-semibold text-gray-400 transition hover:border-brand-400 hover:text-brand-600">
            <x-icon name="plus" class="h-3.5 w-3.5" /> Vraag toevoegen
        </button>
    @else
        <h2 class="mb-4 text-lg font-bold text-navy-950">{!! QuoteText::render($d['title'] ?? 'Veelgestelde vragen') !!}</h2>
        @foreach (($d['items'] ?? []) as $item)
            @continue(blank($item['q'] ?? ''))
            <div class="mb-3 rounded-xl bg-gray-50 p-5">
                <p class="mb-1.5 text-sm font-bold text-navy-900">{!! QuoteText::render($item['q']) !!}</p>
                <div class="text-sm text-gray-600">{!! QuoteText::render($item['a'] ?? '') !!}</div>
            </div>
        @endforeach
    @endif
</section>
