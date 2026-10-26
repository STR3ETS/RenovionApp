@php
    use App\Support\QuoteText;

    $isEdit = ($mode ?? 'view') === 'edit';
    $d = $data ?? [];
@endphp

<section class="rounded-2xl border border-gray-200 bg-white p-6 lg:p-8">
    @if ($isEdit)
        <div x-rich="block.data.title" @blur="save()" data-ph="Aanpak & planning" class="mb-1 text-lg font-bold text-navy-950"></div>
        <div x-rich="block.data.intro" @blur="save()" data-ph="Korte inleiding (optioneel)" class="mb-4 text-sm text-gray-500"></div>
        <div class="grid gap-3 sm:grid-cols-2">
            <template x-for="(fase, i) in block.data.items" :key="i">
                <div class="group relative rounded-xl bg-gray-50 p-5">
                    <span class="inline-block rounded-full bg-brand-500 px-2.5 py-0.5 text-xs font-bold text-white" x-text="'Fase ' + (i + 1)"></span>
                    <div x-rich="fase.title" @blur="save()" data-ph="Fasenaam" class="mt-2 text-sm font-bold text-navy-900"></div>
                    <div x-rich="fase.body" @blur="save()" data-ph="Wat gebeurt er in deze fase…" class="mt-1 text-sm leading-relaxed text-gray-600"></div>
                    <button type="button" @click="block.data.items.splice(i, 1); save()" class="absolute top-2 right-2 text-gray-300 opacity-0 transition group-hover:opacity-100 hover:text-red-500" title="Fase verwijderen">
                        <x-icon name="x-mark" class="h-3.5 w-3.5" />
                    </button>
                </div>
            </template>
            <button type="button" @click="block.data.items.push({ title: 'Nieuwe fase', body: '' }); save()"
                    class="flex items-center justify-center gap-1.5 rounded-xl border border-dashed border-gray-300 p-5 text-xs font-semibold text-gray-400 transition hover:border-brand-400 hover:text-brand-600">
                <x-icon name="plus" class="h-3.5 w-3.5" /> Fase toevoegen
            </button>
        </div>
    @else
        <h2 class="mb-1 text-lg font-bold text-navy-950">{!! QuoteText::render($d['title'] ?? 'Aanpak & planning') !!}</h2>
        @if (filled($d['intro'] ?? ''))
            <div class="mb-4 text-sm text-gray-500">{!! QuoteText::render($d['intro']) !!}</div>
        @endif
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach (($d['items'] ?? []) as $fase)
                <div class="rounded-xl bg-gray-50 p-5">
                    <span class="inline-block rounded-full bg-brand-500 px-2.5 py-0.5 text-xs font-bold text-white">Fase {{ $loop->iteration }}</span>
                    <h3 class="mt-2 text-sm font-bold text-navy-900">{!! QuoteText::render($fase['title'] ?? '') !!}</h3>
                    <div class="mt-1 text-sm leading-relaxed text-gray-600">{!! QuoteText::render($fase['body'] ?? '') !!}</div>
                </div>
            @endforeach
        </div>
    @endif
</section>
