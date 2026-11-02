@php
    use App\Models\Budget;
@endphp

<x-layouts.app title="Begrotingen">
    <x-page-header title="Begrotingen" eyebrow="Calculatie" category="calc"
                   subtitle="Met hoofdstukken, normuren en staartkosten. De basis voor offerte en nacalculatie.">
        <a href="{{ route('budgets.create') }}"
           class="flex items-center gap-1.5 rounded-full bg-brand-500 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600">
            <x-icon name="plus" class="h-4 w-4" /> Nieuwe begroting
        </a>
    </x-page-header>

    <form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
        <div class="relative min-w-0 flex-1 sm:max-w-xs">
            <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-500" />
            <input type="search" name="q" value="{{ $zoek }}" placeholder="Zoek op naam of nummer"
                   class="w-full rounded-full border-surface-700 bg-surface-850 py-2 pl-10 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">
        </div>

        @foreach ([['', 'Alles'], [Budget::STATUS_DRAFT, 'Concept'], [Budget::STATUS_FINAL, 'Definitief']] as [$key, $label])
            <a href="{{ route('budgets.index', ['status' => $key ?: null, 'q' => $zoek ?: null]) }}"
               class="flex shrink-0 items-center gap-1.5 rounded-full border px-3.5 py-2 text-sm font-semibold transition {{ $status === $key ? 'border-calc-500 bg-calc-500/10 text-white' : 'border-surface-700 text-slate-400 hover:text-slate-200' }}">
                {{ $label }}
                <span class="text-xs text-slate-500">{{ $key === '' ? $counts->sum() : ($counts[$key] ?? 0) }}</span>
            </a>
        @endforeach
    </form>

    @if ($budgets->isEmpty())
        <x-empty-state icon="calculator" category="calc" title="Nog geen begrotingen"
                       subtitle="Een begroting begint met het hoofdstukskelet dat bij het werk past.">
            <a href="{{ route('budgets.create') }}" class="inline-flex items-center gap-1.5 rounded-full bg-brand-500 px-4 py-2.5 text-sm font-bold text-white">
                <x-icon name="plus" class="h-4 w-4" /> Nieuwe begroting
            </a>
        </x-empty-state>
    @else
        <div class="overflow-hidden rounded-2xl border border-surface-700 bg-surface-800">
            @foreach ($budgets as $budget)
                @php($t = $totals[$budget->id])
                <a href="{{ route('budgets.show', $budget) }}"
                   class="flex items-center gap-3 border-b border-surface-700 border-l-4 border-l-calc-500/60 p-4 transition last:border-b-0 hover:bg-white/5">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-calc-500/15 text-calc-400">
                        <x-icon name="calculator" class="h-5 w-5" />
                    </span>

                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center gap-2">
                            <b class="truncate text-sm font-semibold text-white">{{ $budget->name }}</b>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $budget->isDraft() ? 'bg-white/10 text-slate-300' : 'bg-green-500/15 text-green-300' }}">
                                {{ $budget->isDraft() ? 'Concept' : 'Definitief' }}
                            </span>
                            @if ($t->incompleteLines > 0)
                                <span class="rounded-full bg-amber-500/15 px-2 py-0.5 text-[10px] font-bold text-amber-300">
                                    {{ $t->incompleteLines }} zonder prijs
                                </span>
                            @endif
                        </span>
                        <small class="mt-0.5 block truncate text-xs text-slate-400">
                            {{ $budget->number }},
                            {{ $budget->project?->name ?? $budget->lead?->customer?->name ?? $budget->customer?->name ?? 'geen dossier' }},
                            {{ $budget->chapters_count }} {{ $budget->chapters_count === 1 ? 'hoofdstuk' : 'hoofdstukken' }},
                            {{ $t->lineCount }} {{ $t->lineCount === 1 ? 'regel' : 'regels' }}
                        </small>
                    </span>

                    <span class="shrink-0 text-right">
                        <b class="block text-sm font-bold text-white">EUR {{ number_format($t->totalExcludingVat, 0, ',', '.') }}</b>
                        <small class="block text-xs text-slate-500">
                            {{ number_format($t->hours, 0, ',', '.') }} uur, marge {{ number_format($t->margin, 1, ',', '.') }}%
                        </small>
                    </span>
                </a>
            @endforeach
        </div>

        <div class="mt-4">{{ $budgets->links() }}</div>
    @endif
</x-layouts.app>
