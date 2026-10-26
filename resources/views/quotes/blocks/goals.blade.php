@php
    use App\Support\QuoteText;

    $isEdit = ($mode ?? 'view') === 'edit';
    $d = $data ?? [];
@endphp

<section class="rounded-2xl border border-gray-200 bg-white p-6 lg:p-8">
    @if ($isEdit)
        <div x-rich="block.data.title" @blur="save()" data-ph="Uw wensen & doelen" class="mb-1 text-lg font-bold text-navy-950"></div>
        <div x-rich="block.data.intro" @blur="save()" data-ph="Korte inleiding (optioneel)" class="mb-3 text-sm text-gray-500"></div>
        <template x-for="(goal, i) in block.data.items" :key="i">
            <div class="group mb-2 flex items-center gap-3">
                <x-icon name="bolt" class="h-4 w-4 shrink-0 text-brand-500" />
                <div x-rich="block.data.items[i]" @blur="save()" data-ph="Doel…" class="min-w-0 flex-1 text-sm font-medium text-navy-900"></div>
                <button type="button" @click="block.data.items.splice(i, 1); save()" class="shrink-0 text-gray-300 opacity-0 transition group-hover:opacity-100 hover:text-red-500">
                    <x-icon name="x-mark" class="h-3.5 w-3.5" />
                </button>
            </div>
        </template>
        <button type="button" @click="block.data.items.push('Nieuw doel'); save()" class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-brand-600 hover:text-brand-500">
            <x-icon name="plus" class="h-3.5 w-3.5" /> Doel toevoegen
        </button>
    @else
        <h2 class="mb-1 text-lg font-bold text-navy-950">{!! QuoteText::render($d['title'] ?? 'Uw wensen & doelen') !!}</h2>
        @if (filled($d['intro'] ?? ''))
            <div class="mb-3 text-sm text-gray-500">{!! QuoteText::render($d['intro']) !!}</div>
        @endif
        @foreach (($d['items'] ?? []) as $goal)
            @continue(blank($goal))
            <div class="mb-2 flex items-start gap-3">
                <x-icon name="bolt" class="mt-0.5 h-4 w-4 shrink-0 text-brand-500" />
                <span class="text-sm font-medium text-navy-900">{!! QuoteText::render($goal) !!}</span>
            </div>
        @endforeach
    @endif
</section>
