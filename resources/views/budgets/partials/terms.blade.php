{{-- Het betaalschema. De percentages horen op te tellen tot honderd procent van
     de aanneemsom, anders blijft er geld onbenoemd. --}}
<div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
    <h2 class="mb-1 text-sm font-bold text-white">Betaal<span class="text-brand-500">schema</span></h2>
    <p class="mb-4 text-xs {{ abs($dekking - 100) < 0.01 ? 'text-slate-500' : 'text-amber-300' }}">
        @if (abs($dekking - 100) < 0.01)
            De termijnen dekken de volle honderd procent.
        @else
            De termijnen tellen op tot {{ rtrim(rtrim(number_format($dekking, 2, ',', '.'), '0'), ',') }} procent.
            Er {{ $dekking < 100 ? 'ontbreekt' : 'staat te veel' }} {{ rtrim(rtrim(number_format(abs(100 - $dekking), 2, ',', '.'), '0'), ',') }} procent.
        @endif
    </p>

    <div class="space-y-1.5">
        @foreach ($budget->terms as $term)
            @php($bedrag = $budget->termAmount($term, $totals))
            <div class="flex items-center gap-2">
                @if ($budget->isDraft())
                    <form method="POST" action="{{ route('budget-terms.update', $term) }}" class="flex min-w-0 flex-1 items-center gap-2">
                        @csrf
                        @method('PATCH')
                        <input type="text" name="description" value="{{ $term->description }}" onchange="this.form.requestSubmit()"
                               class="min-w-0 flex-1 rounded-lg border-0 bg-transparent px-1 py-1 text-sm text-slate-200 focus:bg-surface-850 focus:ring-1 focus:ring-brand-500">
                        <input type="text" name="percentage" value="{{ rtrim(rtrim(number_format((float) $term->percentage, 2, ',', '.'), '0'), ',') }}" onchange="this.form.requestSubmit()"
                               class="w-12 shrink-0 rounded-lg border-0 bg-transparent px-1 py-1 text-right text-sm text-white focus:bg-surface-850 focus:ring-1 focus:ring-brand-500">
                        <span class="shrink-0 text-xs text-slate-500">%</span>
                    </form>
                @else
                    <span class="min-w-0 flex-1 truncate text-sm text-slate-200">{{ $term->description }}</span>
                    <span class="shrink-0 text-xs text-slate-500">{{ rtrim(rtrim(number_format((float) $term->percentage, 2, ',', '.'), '0'), ',') }}%</span>
                @endif

                <span class="w-24 shrink-0 text-right text-sm font-semibold text-white">EUR {{ number_format($bedrag, 0, ',', '.') }}</span>
                <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $term->badgeClasses() }}">{{ $term->statusLabel() }}</span>

                @if ($budget->isDraft())
                    <form method="POST" action="{{ route('budget-terms.destroy', $term) }}" class="shrink-0">
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
        <span class="text-sm font-bold text-white">Aanneemsom</span>
        <span class="text-sm font-bold text-white">EUR {{ number_format($totals->contractSum(), 0, ',', '.') }}</span>
    </div>

    @if ($budget->isDraft())
        <form method="POST" action="{{ route('budget-terms.store', $budget) }}" class="mt-3 flex gap-2">
            @csrf
            <input type="text" name="description" required placeholder="Nieuwe termijn"
                   class="min-w-0 flex-1 rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">
            <input type="text" name="percentage" required placeholder="%" value="0"
                   class="w-16 shrink-0 rounded-xl border-surface-600 bg-surface-850 py-2 text-right text-sm text-white focus:border-brand-500 focus:ring-brand-500">
            <button type="submit" class="shrink-0 rounded-xl border border-surface-600 px-3 py-2 text-slate-300 transition hover:text-white">
                <x-icon name="plus" class="h-4 w-4" />
            </button>
        </form>
    @endif
</div>
