@php
    use App\Support\QuoteText;

    $isEdit = ($mode ?? 'view') === 'edit';
    $d = $data ?? [];
@endphp

<section class="rounded-2xl border border-gray-200 bg-white p-6 lg:p-8">
    @if ($isEdit)
        <div x-rich="block.data.title" @blur="save()" data-ph="Wat onze klanten zeggen" class="mb-4 text-lg font-bold text-navy-950"></div>
        <div class="grid gap-3 sm:grid-cols-2">
            <template x-for="(item, i) in block.data.items" :key="i">
                <div class="group relative rounded-xl border border-brand-100 bg-brand-50 p-5">
                    <div x-rich="item.quote" @blur="save()" data-ph="Wat zei de klant…" class="mb-3 text-sm leading-relaxed text-navy-900"></div>
                    <div class="flex items-center gap-3 border-t border-brand-100 pt-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-500 text-sm font-bold text-white" x-text="(item.name || '?').trim().charAt(0).toUpperCase()"></span>
                        <div class="min-w-0 flex-1">
                            <div x-rich="item.name" @blur="save()" data-ph="Naam" class="text-sm font-bold text-navy-900"></div>
                            <div x-rich="item.context" @blur="save()" data-ph="Project of plaats" class="text-xs text-gray-500"></div>
                        </div>
                    </div>
                    <button type="button" @click="block.data.items.splice(i, 1); save()" class="absolute top-2 right-2 text-gray-300 opacity-0 transition group-hover:opacity-100 hover:text-red-500" title="Verwijderen">
                        <x-icon name="x-mark" class="h-3.5 w-3.5" />
                    </button>
                </div>
            </template>
            <button type="button" @click="block.data.items.push({ name: 'Naam', context: '', quote: '' }); save()"
                    class="flex min-h-[8rem] flex-col items-center justify-center gap-1.5 rounded-xl border border-dashed border-brand-200 p-5 text-xs font-semibold text-brand-500/70 transition hover:border-brand-400 hover:text-brand-600">
                <x-icon name="plus" class="h-4 w-4" /> Ervaring toevoegen
            </button>
        </div>
    @else
        <h2 class="mb-4 text-lg font-bold text-navy-950">{!! QuoteText::render($d['title'] ?? 'Wat onze klanten zeggen') !!}</h2>
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach (($d['items'] ?? []) as $item)
                @continue(blank($item['quote'] ?? ''))
                <div class="rounded-xl border border-brand-100 bg-brand-50 p-5">
                    <div class="mb-3 text-sm leading-relaxed text-navy-900">{!! QuoteText::render($item['quote']) !!}</div>
                    <div class="flex items-center gap-3 border-t border-brand-100 pt-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-500 text-sm font-bold text-white">{{ mb_strtoupper(mb_substr(\App\Support\QuoteText::plain($item['name'] ?? '?') ?: '?', 0, 1)) }}</span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-bold text-navy-900">{!! QuoteText::render($item['name'] ?? '') !!}</p>
                            @if (filled($item['context'] ?? ''))
                                <p class="truncate text-xs text-gray-500">{!! QuoteText::render($item['context']) !!}</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</section>
