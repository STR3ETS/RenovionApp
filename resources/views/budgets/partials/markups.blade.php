{{-- De staart rekent cumulatief: winst en risico gaat over de directe kosten
     plus de algemene kosten. Daarom staat het bedrag per post erbij. --}}
<div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
    <h2 class="mb-1 text-sm font-bold text-white">De <span class="text-brand-500">staart</span></h2>
    <p class="mb-4 text-xs text-slate-500">Elke post rekent over het bedrag inclusief de vorige.</p>

    <div class="space-y-2">
        @foreach ($budget->markups as $index => $markup)
            @php($berekend = $totals->markups[$index] ?? null)
            <div class="flex items-center gap-2">
                @if ($budget->isDraft())
                    <form method="POST" action="{{ route('budget-markups.update', $markup) }}" class="flex min-w-0 flex-1 items-center gap-2">
                        @csrf
                        @method('PATCH')
                        <input type="text" name="name" value="{{ $markup->name }}" onchange="this.form.requestSubmit()"
                               class="min-w-0 flex-1 rounded-lg border-0 bg-transparent px-1 py-1 text-sm text-slate-200 focus:bg-surface-850 focus:ring-1 focus:ring-brand-500">
                        <input type="text" name="percentage" value="{{ rtrim(rtrim(number_format((float) $markup->percentage, 3, ',', '.'), '0'), ',') }}" onchange="this.form.requestSubmit()"
                               class="w-14 shrink-0 rounded-lg border-0 bg-transparent px-1 py-1 text-right text-sm text-white focus:bg-surface-850 focus:ring-1 focus:ring-brand-500">
                        <span class="shrink-0 text-xs text-slate-500">%</span>
                    </form>
                @else
                    <span class="min-w-0 flex-1 truncate text-sm text-slate-200">{{ $markup->name }}</span>
                    <span class="shrink-0 text-sm text-slate-400">{{ rtrim(rtrim(number_format((float) $markup->percentage, 3, ',', '.'), '0'), ',') }}%</span>
                @endif

                <span class="w-24 shrink-0 text-right text-sm font-semibold text-white">
                    EUR {{ number_format($berekend['amount'] ?? 0, 0, ',', '.') }}
                </span>

                @if ($budget->isDraft())
                    <form method="POST" action="{{ route('budget-markups.destroy', $markup) }}" class="shrink-0">
                        @csrf
                        @method('DELETE')
                        <button type="submit" title="Verwijderen" class="text-slate-600 transition hover:text-red-400">
                            <x-icon name="trash" class="h-3.5 w-3.5" />
                        </button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>

    <div class="mt-3 flex items-center justify-between border-t border-surface-700 pt-3">
        <span class="text-sm font-bold text-white">Totaal staart</span>
        <span class="text-sm font-bold text-white">EUR {{ number_format($totals->markupTotal, 0, ',', '.') }}</span>
    </div>

    @if ($budget->isDraft())
        <form method="POST" action="{{ route('budget-markups.store', $budget) }}" class="mt-3 flex gap-2">
            @csrf
            <input type="text" name="name" required placeholder="Nieuwe post"
                   class="min-w-0 flex-1 rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">
            <input type="text" name="percentage" required placeholder="%" value="0"
                   class="w-16 shrink-0 rounded-xl border-surface-600 bg-surface-850 py-2 text-right text-sm text-white focus:border-brand-500 focus:ring-brand-500">
            <button type="submit" class="shrink-0 rounded-xl border border-surface-600 px-3 py-2 text-slate-300 transition hover:text-white">
                <x-icon name="plus" class="h-4 w-4" />
            </button>
        </form>
    @endif

    {{-- Marge en opslag zijn twee kanten van hetzelfde getal. --}}
    <div class="mt-4 grid grid-cols-2 gap-3 border-t border-surface-700 pt-4">
        <div>
            <p class="text-[11px] font-bold tracking-widest text-slate-500 uppercase">Marge</p>
            <p class="text-lg font-bold text-white">{{ rtrim(rtrim(number_format($totals->margin, 2, ',', '.'), '0'), ',') }}%</p>
            <p class="text-xs text-slate-500">van de aanneemsom</p>
        </div>
        <div>
            <p class="text-[11px] font-bold tracking-widest text-slate-500 uppercase">Opslag</p>
            <p class="text-lg font-bold text-white">{{ rtrim(rtrim(number_format($totals->surcharge, 2, ',', '.'), '0'), ',') }}%</p>
            <p class="text-xs text-slate-500">op de kosten</p>
        </div>
    </div>
</div>
