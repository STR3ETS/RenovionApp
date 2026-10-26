@php
    use App\Support\QuoteText;

    $isEdit = ($mode ?? 'view') === 'edit';
    $d = $data ?? [];
@endphp

<section class="rounded-2xl border border-gray-200 bg-white p-6 lg:p-8">
    @if ($isEdit)
        <div x-rich="block.data.title" @blur="save()" data-ph="Bevindingen uit de opname" class="mb-4 text-lg font-bold text-navy-950"></div>
        <div class="grid gap-6 sm:grid-cols-2">
            <div>
                <h3 class="mb-3 flex items-center gap-2 text-sm font-bold text-navy-900"><x-icon name="check" class="h-4 w-4 text-green-600" /> Wat goed zit</h3>
                <template x-for="(p, i) in block.data.good" :key="i">
                    <div class="group mb-2 flex items-center gap-2">
                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-green-500"></span>
                        <div x-rich="block.data.good[i]" @blur="save()" data-ph="Punt…" class="min-w-0 flex-1 text-sm text-gray-600"></div>
                        <button type="button" @click="block.data.good.splice(i, 1); save()" class="shrink-0 text-gray-300 opacity-0 transition group-hover:opacity-100 hover:text-red-500">
                            <x-icon name="x-mark" class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </template>
                <button type="button" @click="block.data.good.push('Nieuw punt'); save()" class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-green-600 hover:text-green-500">
                    <x-icon name="plus" class="h-3.5 w-3.5" /> Toevoegen
                </button>
            </div>
            <div>
                <h3 class="mb-3 flex items-center gap-2 text-sm font-bold text-navy-900"><x-icon name="exclamation-triangle" class="h-4 w-4 text-amber-500" /> Vraagt aandacht</h3>
                <template x-for="(p, i) in block.data.attention" :key="i">
                    <div class="group mb-2 flex items-center gap-2">
                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-amber-400"></span>
                        <div x-rich="block.data.attention[i]" @blur="save()" data-ph="Punt…" class="min-w-0 flex-1 text-sm text-gray-600"></div>
                        <button type="button" @click="block.data.attention.splice(i, 1); save()" class="shrink-0 text-gray-300 opacity-0 transition group-hover:opacity-100 hover:text-red-500">
                            <x-icon name="x-mark" class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </template>
                <button type="button" @click="block.data.attention.push('Nieuw punt'); save()" class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-amber-600 hover:text-amber-500">
                    <x-icon name="plus" class="h-3.5 w-3.5" /> Toevoegen
                </button>
            </div>
        </div>
    @else
        <h2 class="mb-4 text-lg font-bold text-navy-950">{!! QuoteText::render($d['title'] ?? 'Bevindingen uit de opname') !!}</h2>
        <div class="grid gap-6 sm:grid-cols-2">
            <div>
                <h3 class="mb-3 flex items-center gap-2 text-sm font-bold text-navy-900"><x-icon name="check" class="h-4 w-4 text-green-600" /> Wat goed zit</h3>
                @foreach (($d['good'] ?? []) as $point)
                    @continue(blank($point))
                    <div class="mb-2 flex items-start gap-2">
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-green-500"></span>
                        <span class="text-sm text-gray-600">{!! QuoteText::render($point) !!}</span>
                    </div>
                @endforeach
            </div>
            <div>
                <h3 class="mb-3 flex items-center gap-2 text-sm font-bold text-navy-900"><x-icon name="exclamation-triangle" class="h-4 w-4 text-amber-500" /> Vraagt aandacht</h3>
                @foreach (($d['attention'] ?? []) as $point)
                    @continue(blank($point))
                    <div class="mb-2 flex items-start gap-2">
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-400"></span>
                        <span class="text-sm text-gray-600">{!! QuoteText::render($point) !!}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</section>
