@php
    $eur = fn (float $v) => 'EUR '.number_format($v, 0, ',', '.');
    $num = fn (float $v, int $d = 1) => rtrim(rtrim(number_format($v, $d, ',', '.'), '0'), ',');
    $pill = fn (?float $v) => $v === null
        ? 'bg-white/10 text-slate-400'
        : ($v > 5 ? 'bg-red-500/15 text-red-300' : ($v > 0 ? 'bg-amber-500/15 text-amber-300' : 'bg-green-500/15 text-green-300'));
@endphp

@if ($review === null)
    <x-empty-state icon="calculator" category="calc" title="Nog geen nacalculatie mogelijk"
                   subtitle="Dit project heeft een begroting nodig om de werkelijke kosten tegen af te kunnen zetten." />
@else
    <div class="space-y-4">
        <x-kpi-row class="mb-0">
            <x-kpi dark label="Marge verwacht" :value="$num($review['margin_expected']).'%'"
                   :alert="$review['margin_expected'] < $review['margin_budgeted'] - 2"
                   :sub="'begroot '.$num($review['margin_budgeted']).'%'" />
            <x-kpi label="Begroot" :value="$eur($review['budgeted'])" sub="directe kosten" />
            <x-kpi label="Werkelijk" :value="$eur($review['actual'])" sub="geboekt tot nu toe" />
            <x-kpi label="Verwacht" :value="$eur($review['expected'])" sub="eindbeeld" />
        </x-kpi-row>

        <section class="overflow-hidden rounded-2xl border border-surface-700 bg-surface-800">
            <div class="border-b border-surface-700 p-5">
                <h2 class="text-sm font-bold text-white">Per <span class="text-brand-500">kostensoort</span></h2>
                <p class="mt-0.5 text-xs text-slate-500">
                    Voortgang {{ $review['progress'] }}%, {{ $num($review['hours_actual']) }} van {{ $num($review['hours_budgeted']) }} begrote uren geboekt.
                    Urenfactor {{ $num($review['efficiency'], 2) }}, boven één is meer uren dan begroot.
                </p>
            </div>

            <div class="overflow-x-auto p-5">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-surface-700 text-[10px] font-bold tracking-wider text-slate-500 uppercase">
                            <th class="pb-2 text-left">Kostensoort</th>
                            <th class="pb-2 text-right">Begroot</th>
                            <th class="pb-2 text-right">Werkelijk</th>
                            <th class="pb-2 text-right">Verwacht</th>
                            <th class="pb-2 text-right">Afwijking</th>
                            <th class="pb-2 pl-4 text-left">Toelichting</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($review['rows'] as $row)
                            <tr class="border-b border-surface-700/60">
                                <td class="py-2.5 font-semibold text-slate-200">{{ $row['name'] }}</td>
                                <td class="py-2.5 text-right text-slate-400">{{ $eur($row['budgeted']) }}</td>
                                <td class="py-2.5 text-right text-white">{{ $eur($row['actual']) }}</td>
                                <td class="py-2.5 text-right font-semibold text-white">{{ $eur($row['expected']) }}</td>
                                <td class="py-2.5 text-right">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $pill($row['variance']) }}">
                                        {{ $row['variance'] === null ? 'n.v.t.' : (($row['variance'] > 0 ? '+' : '').$num($row['variance']).'%') }}
                                    </span>
                                </td>
                                <td class="py-2.5 pl-4 text-xs text-slate-500">{{ $row['note'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endif
