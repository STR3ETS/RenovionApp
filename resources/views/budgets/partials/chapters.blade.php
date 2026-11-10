@php
    $rate = (float) $budget->hourly_rate;
    $chapterTotals = collect($totals->chapters)->keyBy('code');
    $hoogste = max(1, collect($totals->chapters)->max('total') ?: 1);
@endphp

<div class="rounded-2xl border border-surface-700 bg-surface-800">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-surface-700 p-5">
        <div>
            <h2 class="text-sm font-bold text-white">Hoofd<span class="text-brand-500">stukken</span></h2>
            <p class="mt-0.5 text-xs text-slate-400">
                {{ $totals->lineCount }} {{ $totals->lineCount === 1 ? 'regel' : 'regels' }},
                tarief {{ 'EUR '.number_format($rate, 2, ',', '.') }} per uur
            </p>
        </div>

        @if ($budget->isDraft())
            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open" @click.outside="open = false"
                        class="flex items-center gap-1.5 rounded-full border border-surface-600 px-3.5 py-2 text-sm font-semibold text-slate-200 transition hover:text-white">
                    <x-icon name="plus" class="h-4 w-4" /> Hoofdstuk
                </button>

                <form x-show="open" x-cloak method="POST" action="{{ route('budget-chapters.store', $budget) }}"
                      class="absolute right-0 z-20 mt-2 w-72 rounded-2xl border border-surface-700 bg-surface-800 p-4 shadow-2xl shadow-black/40">
                    @csrf
                    <label for="chapter-code" class="mb-1 block text-xs font-semibold text-slate-300">Nummer uit de standaard</label>
                    <select name="code" id="chapter-code"
                            class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                        @foreach ($chapterLibrary as $code => $name)
                            <option value="{{ $code }}">{{ $code }} {{ $name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="mt-3 w-full rounded-xl bg-brand-500 py-2.5 text-sm font-bold text-white">Toevoegen</button>
                </form>
            </div>
        @endif
    </div>

    @if ($budget->chapters->isEmpty())
        <div class="p-5">
            <x-empty-state icon="calculator" category="calc" title="Nog geen hoofdstukken"
                           subtitle="Voeg een hoofdstuk toe om regels te kunnen begroten." />
        </div>
    @else
        <div class="divide-y divide-surface-700">
            @foreach ($budget->chapters as $chapter)
                @php
                    $total = (float) ($chapterTotals[$chapter->code]['total'] ?? 0);
                    $regels = $chapter->paragraphs->sum(fn ($p) => $p->lines->count());
                @endphp

                <div x-data="{ open: {{ $regels > 0 ? 'true' : 'false' }} }">
                    <button @click="open = !open" class="flex w-full items-center gap-3 p-4 text-left transition hover:bg-white/5">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-calc-500/15 text-xs font-bold text-calc-300">
                            {{ $chapter->code }}
                        </span>

                        <span class="min-w-0 flex-1">
                            <b class="block truncate text-sm font-semibold text-white">{{ $chapter->name }}</b>
                            <span class="mt-1 flex items-center gap-2">
                                <span class="h-1 w-24 overflow-hidden rounded-full bg-surface-700">
                                    <span class="block h-full rounded-full bg-calc-500" style="width: {{ round($total / $hoogste * 100) }}%"></span>
                                </span>
                                <small class="text-xs text-slate-500">{{ $regels }} {{ $regels === 1 ? 'regel' : 'regels' }}</small>
                            </span>
                        </span>

                        <span class="shrink-0 text-right">
                            <b class="block text-sm font-bold text-white">EUR {{ number_format($total, 0, ',', '.') }}</b>
                            <small class="block text-xs text-slate-500">
                                {{ $totals->direct > 0 ? number_format($total / $totals->direct * 100, 1, ',', '.') : '0,0' }}% van de kosten
                            </small>
                        </span>

                        <x-icon name="chevron-down" class="h-4 w-4 shrink-0 text-slate-500" ::class="open ? 'rotate-180' : ''" />
                    </button>

                    <div x-show="open" x-cloak class="border-t border-surface-700 bg-surface-850 px-4 py-3">
                        @foreach ($chapter->paragraphs as $paragraph)
                            @include('budgets.partials.paragraph', ['paragraph' => $paragraph])
                        @endforeach

                        @if ($budget->isDraft())
                            <form method="POST" action="{{ route('budget-paragraphs.store', $chapter) }}" class="mt-2 flex gap-2">
                                @csrf
                                <input type="text" name="name" required placeholder="Nieuwe paragraaf, bijvoorbeeld: Voorzetwanden begane grond"
                                       class="min-w-0 flex-1 rounded-xl border-surface-600 bg-surface-800 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">
                                <button type="submit" class="shrink-0 rounded-xl border border-surface-600 px-3.5 py-2 text-sm font-semibold text-slate-200 transition hover:text-white">
                                    Paragraaf
                                </button>
                            </form>

                            <form method="POST" action="{{ route('budget-chapters.destroy', $chapter) }}" class="mt-2 text-right">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-semibold text-slate-500 transition hover:text-red-400">
                                    Hoofdstuk {{ $chapter->code }} verwijderen
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
