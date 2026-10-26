@php
    use App\Support\QuoteText;

    $isEdit = ($mode ?? 'view') === 'edit';
    $d = $data ?? [];
@endphp

<section class="rounded-2xl border border-gray-200 bg-white p-6 lg:p-8">
    @if ($isEdit)
        <div x-rich="block.data.title" @blur="save()" data-ph="Titel" class="mb-3 text-lg font-bold text-navy-950"></div>
        <div x-rich="block.data.intro" @blur="save()" data-ph="Korte inleiding (optioneel)" class="mb-3 text-sm text-gray-600"></div>
        <ul class="space-y-1.5">
            <template x-for="(item, i) in block.data.items" :key="i">
                <li class="group flex items-start gap-2 text-sm text-gray-600">
                    <x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-brand-500" />
                    <div x-rich="block.data.items[i]" @blur="save()" data-ph="Punt…" class="min-w-0 flex-1"></div>
                    <button type="button" @click="block.data.items.splice(i, 1); save()" class="text-gray-300 opacity-0 transition group-hover:opacity-100 hover:text-red-500" title="Punt verwijderen">
                        <x-icon name="x-mark" class="h-3.5 w-3.5" />
                    </button>
                </li>
            </template>
        </ul>
        <button type="button" @click="block.data.items.push('Nieuw punt'); save()" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-brand-600 hover:text-brand-500">
            <x-icon name="plus" class="h-3.5 w-3.5" /> Punt toevoegen
        </button>
    @else
        <h2 class="mb-3 text-lg font-bold text-navy-950">{!! QuoteText::render($d['title'] ?? '') !!}</h2>
        @if (filled($d['intro'] ?? ''))
            <div class="mb-3 text-sm text-gray-600">{!! QuoteText::render($d['intro']) !!}</div>
        @endif
        <ul class="space-y-1.5">
            @foreach (($d['items'] ?? []) as $item)
                @continue(blank($item))
                <li class="flex items-start gap-2 text-sm text-gray-600">
                    <x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-brand-500" />
                    <span class="min-w-0">{!! QuoteText::render($item) !!}</span>
                </li>
            @endforeach
        </ul>
    @endif
</section>
