@php
    use App\Support\QuoteText;

    $isEdit = ($mode ?? 'view') === 'edit';
    $d = $data ?? [];
@endphp

<section class="rounded-2xl border border-gray-200 bg-white p-6 lg:p-8">
    @if ($isEdit)
        <div x-rich="block.data.title" @blur="save()" data-ph="Samenvatting van het voorstel" class="mb-3 text-lg font-bold text-navy-950"></div>
        <div x-rich="block.data.text" @blur="save()" data-ph="Korte introductie…" class="mb-4 text-sm leading-relaxed text-gray-600"></div>

        <div class="grid gap-3 sm:grid-cols-2">
            <template x-for="(h, i) in block.data.highlights" :key="i">
                <div class="group relative flex items-start gap-2 rounded-xl border border-brand-100 bg-brand-50 px-4 py-3">
                    <x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-brand-500" />
                    <div x-rich="block.data.highlights[i]" @blur="save()" data-ph="Highlight…" class="min-w-0 flex-1 text-sm font-medium text-navy-900"></div>
                    <button type="button" @click="block.data.highlights.splice(i, 1); save()"
                            class="absolute top-1.5 right-1.5 text-gray-300 opacity-0 transition group-hover:opacity-100 hover:text-red-500" title="Verwijderen">
                        <x-icon name="x-mark" class="h-3.5 w-3.5" />
                    </button>
                </div>
            </template>
            <button type="button" @click="block.data.highlights.push('Nieuw punt'); save()"
                    class="flex items-center justify-center gap-1.5 rounded-xl border border-dashed border-brand-200 px-4 py-3 text-xs font-semibold text-brand-500/70 transition hover:border-brand-400 hover:text-brand-600">
                <x-icon name="plus" class="h-3.5 w-3.5" /> Toevoegen
            </button>
        </div>
    @else
        <h2 class="mb-3 text-lg font-bold text-navy-950">{!! QuoteText::render($d['title'] ?? 'Samenvatting van het voorstel') !!}</h2>
        @if (filled($d['text'] ?? ''))
            <div class="quote-prose mb-4 text-sm leading-relaxed text-gray-600">{!! QuoteText::render($d['text']) !!}</div>
        @endif
        @if (! empty($d['highlights']))
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($d['highlights'] as $highlight)
                    @continue(blank($highlight))
                    <div class="flex items-start gap-2 rounded-xl border border-brand-100 bg-brand-50 px-4 py-3">
                        <x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-brand-500" />
                        <span class="text-sm font-medium text-navy-900">{!! QuoteText::render($highlight) !!}</span>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</section>
