@php
    $eur = fn (float $v) => '€ '.number_format($v, 0, ',', '.');
@endphp

@if ($budgets->isEmpty())
    <x-empty-state icon="calculator" category="calc" title="Nog geen begroting"
                   subtitle="Een begroting met hoofdstukken is de basis voor de offerte en later voor de nacalculatie.">
        @can('manage-crm')
            <a href="{{ route('budgets.create', ['project' => $project->id]) }}"
               class="btn pri">
                <x-icon name="plus" class="h-4 w-4" /> Begroting maken
            </a>
        @endcan
    </x-empty-state>
@else
    <div class="space-y-4">
        @foreach ($budgets as $budget)
            @php($t = $budget->totals())

            <section class="overflow-hidden rounded-2xl border border-surface-700 bg-surface-800">
                <div class="flex flex-wrap items-center gap-3 border-b border-surface-700 p-5">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-calc-500/15 text-calc-400">
                        <x-icon name="calculator" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <b class="text-sm font-bold text-ink">{{ $budget->name }}</b>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $budget->isDraft() ? 'bg-ink/10 text-ink2' : 'bg-green-500/15 text-green-300' }}">
                                {{ $budget->isDraft() ? 'Concept' : 'Definitief' }}
                            </span>
                            @if ($t->incompleteLines > 0)
                                <span class="rounded-full bg-amber-500/15 px-2 py-0.5 text-[10px] font-bold text-amber-300">{{ $t->incompleteLines }} zonder prijs</span>
                            @endif
                        </div>
                        <p class="text-xs text-muted">{{ $budget->number }}, versie {{ $budget->version }}, {{ $t->lineCount }} regels</p>
                    </div>
                    <a href="{{ route('budgets.show', $budget) }}"
                       class="btn sm shrink-0">
                        Openen
                    </a>
                </div>

                <div class="grid grid-cols-2 gap-4 p-5 lg:grid-cols-5">
                    @foreach ([
                        ['Directe kosten', $eur($t->direct), rtrim(rtrim(number_format($t->hours, 1, ',', '.'), '0'), ',').' uur'],
                        ['Staart', $eur($t->markupTotal), 'opslag '.rtrim(rtrim(number_format($t->surcharge, 1, ',', '.'), '0'), ',').'%'],
                        ['Aanneemsom', $eur($t->totalExcludingVat), 'marge '.rtrim(rtrim(number_format($t->margin, 1, ',', '.'), '0'), ',').'%'],
                        ['Meerwerk akkoord', $eur($t->approvedExtras), $eur($t->openOptions).' nog open'],
                        ['Inclusief btw', $eur($t->totalIncludingVat), rtrim(rtrim(number_format($t->vatPercentage, 0, ',', '.'), '0'), ',').'% btw'],
                    ] as [$label, $value, $sub])
                        <div>
                            <p class="text-[11px] font-bold tracking-widest text-faint uppercase">{{ $label }}</p>
                            <p class="mt-1 text-base font-bold text-ink">{{ $value }}</p>
                            <p class="text-xs text-muted">{{ $sub }}</p>
                        </div>
                    @endforeach
                </div>

                {{-- De hoofdstukken met hun aandeel --}}
                @if ($t->chapters !== [])
                    @php($hoogste = max(1, collect($t->chapters)->max('total') ?: 1))
                    <div class="space-y-2 border-t border-surface-700 p-5">
                        @foreach ($t->chapters as $chapter)
                            <div class="flex items-center gap-3">
                                <span class="w-8 shrink-0 font-mono text-xs text-faint">{{ $chapter['code'] }}</span>
                                <span class="min-w-0 flex-1 truncate text-sm text-ink">{{ $chapter['name'] }}</span>
                                <span class="h-1.5 w-24 shrink-0 overflow-hidden rounded-full bg-surface-700">
                                    <span class="block h-full rounded-full bg-calc-500" style="width: {{ round($chapter['total'] / $hoogste * 100) }}%"></span>
                                </span>
                                <span class="w-24 shrink-0 text-right text-sm font-semibold text-ink">{{ $eur($chapter['total']) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        @endforeach
    </div>
@endif
