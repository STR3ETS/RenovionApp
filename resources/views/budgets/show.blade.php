@php
    $eur = fn (float $v, int $d = 0) => 'EUR '.number_format($v, $d, ',', '.');
    $num = fn (float $v, int $d = 2) => rtrim(rtrim(number_format($v, $d, ',', '.'), '0'), ',');
    $labels = [
        'arbeid' => ['Arbeid', 'bg-brand-500'],
        'materiaal' => ['Materiaal', 'bg-lead-500'],
        'materieel' => ['Materieel', 'bg-calc-500'],
        'onderaanneming' => ['Onderaanneming', 'bg-offerte-500'],
        'overig' => ['Overig', 'bg-surface-600'],
    ];
    $shares = $totals->categoryShares();
    $dekking = $budget->termsCoverage();
@endphp

<x-layouts.app :title="$budget->name">
    <div class="mb-4">
        <a href="{{ route('budgets.index') }}" class="flex items-center gap-1 text-sm font-semibold text-slate-400 transition hover:text-white">
            <x-icon name="chevron-left" class="h-4 w-4" /> Begrotingen
        </a>
    </div>

    <x-page-header :title="$budget->name" :eyebrow="$budget->number.', versie '.$budget->version" category="calc"
                   :subtitle="collect([$budget->project?->name, $budget->location, $budget->author])->filter()->join(', ')">
        <span class="rounded-full px-3 py-1.5 text-xs font-bold {{ $budget->isDraft() ? 'bg-white/10 text-slate-300' : 'bg-green-500/15 text-green-300' }}">
            {{ $budget->isDraft() ? 'Concept' : 'Definitief' }}
        </span>

        @if ($budget->isDraft())
            <form method="POST" action="{{ route('budgets.finalise', $budget) }}">
                @csrf
                <button type="submit" class="flex items-center gap-1.5 rounded-full bg-brand-500 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600">
                    <x-icon name="lock-closed" class="h-4 w-4" /> Definitief maken
                </button>
            </form>
        @else
            <form method="POST" action="{{ route('budgets.reopen', $budget) }}">
                @csrf
                <button type="submit" class="flex items-center gap-1.5 rounded-full border border-surface-600 px-4 py-2.5 text-sm font-semibold text-slate-200 transition hover:text-white">
                    <x-icon name="arrow-path" class="h-4 w-4" /> Heropenen
                </button>
            </form>
        @endif
    </x-page-header>

    {{-- De getallen die het gesprek bepalen --}}
    <section data-motion-fan class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-5">
        @foreach ([
            ['Directe kosten', $eur($totals->direct), $num($totals->hours, 0).' uur', 'bg-brand-500', 'text-brand-400'],
            ['Staart', $eur($totals->markupTotal), 'opslag '.$num($totals->surcharge, 1).'%', 'bg-lead-500', 'text-lead-400'],
            ['Aanneemsom excl. btw', $eur($totals->totalExcludingVat), 'marge '.$num($totals->margin, 1).'%', 'bg-project-500', 'text-project-400'],
            ['Inclusief btw', $eur($totals->totalIncludingVat), $num($totals->vatPercentage, 0).'% btw', 'bg-calc-500', 'text-calc-400'],
            ['Per m2', $totals->pricePerSquareMetre === null ? 'n.v.t.' : $eur($totals->pricePerSquareMetre), $budget->floor_area ? $num((float) $budget->floor_area, 0).' m2' : 'geen oppervlak', 'bg-planning-500', 'text-planning-400'],
        ] as [$label, $value, $sub, $accent, $labelClass])
            <div class="motion-lift overflow-hidden rounded-2xl border border-surface-700 bg-surface-800">
                <span class="block h-[3px] w-full {{ $accent }}"></span>
                <div class="p-4">
                    <p class="text-[11px] font-bold tracking-widest uppercase {{ $labelClass }}">{{ $label }}</p>
                    <p data-count class="mt-1 text-lg font-bold text-white lg:text-xl">{{ $value }}</p>
                    <p class="mt-0.5 text-xs text-slate-400">{{ $sub }}</p>
                </div>
            </div>
        @endforeach
    </section>

    @if ($totals->incompleteLines > 0)
        <div class="mb-4 flex items-start gap-3 rounded-2xl border border-amber-500/25 bg-amber-500/10 p-4">
            <x-icon name="exclamation-triangle" class="h-5 w-5 shrink-0 text-amber-300" />
            <p class="text-sm text-slate-200">
                <b class="text-white">{{ $totals->incompleteLines }} {{ $totals->incompleteLines === 1 ? 'regel heeft' : 'regels hebben' }} een hoeveelheid zonder prijs.</b>
                Die tellen nu voor nul mee. Definitief maken lukt pas als ze gevuld zijn.
            </p>
        </div>
    @endif

    <div class="grid gap-4 xl:grid-cols-3">
        <div class="space-y-4 xl:col-span-2">
            @include('budgets.partials.chapters')
        </div>

        <div class="space-y-4">
            {{-- Waar het geld zit --}}
            <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-4 text-sm font-bold text-white">Waar het <span class="text-brand-500">geld zit</span></h2>

                <div class="mb-4 flex h-2 overflow-hidden rounded-full bg-surface-700">
                    @foreach ($labels as $key => [$label, $colour])
                        @if ($shares[$key] > 0)
                            <span class="{{ $colour }}" style="width: {{ $shares[$key] }}%" title="{{ $label }} {{ $num($shares[$key], 1) }}%"></span>
                        @endif
                    @endforeach
                </div>

                <div class="space-y-2">
                    @foreach ($labels as $key => [$label, $colour])
                        <div class="flex items-center gap-2.5 text-sm">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $colour }}"></span>
                            <span class="flex-1 text-slate-300">{{ $label }}</span>
                            <span class="font-semibold text-white">{{ $eur($totals->categories[$key]) }}</span>
                            <span class="w-12 text-right text-xs text-slate-500">{{ $num($shares[$key], 0) }}%</span>
                        </div>
                    @endforeach
                </div>
            </div>

            @include('budgets.partials.markups')
            @include('budgets.partials.options')
            @include('budgets.partials.terms')
            @include('budgets.partials.scope')
        </div>
    </div>
</x-layouts.app>
