@php
    $eur = fn (float $v) => 'EUR '.number_format($v, 0, ',', '.');
    $d = $data;
    $maxMonth = max(1, collect($d['per_month'])->max() ?: 1);
@endphp

<x-layouts.app title="Marketing">
    <x-page-header title="Marketing" eyebrow="Inzicht" category="planning"
                   subtitle="Niet hoeveel aanvragen er binnenkwamen, maar welke bron werk oplevert.">
        <form method="GET">
            <select name="periode" onchange="this.form.submit()"
                    class="rounded-full border-surface-700 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                @foreach ($periods as $key => $label)
                    <option value="{{ $key }}" @selected($d['period'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </form>
    </x-page-header>

    <x-kpi-row>
        <x-kpi dark label="Gewonnen waarde" :value="$eur($d['won_value'])" sub="aan opdrachten" />
        <x-kpi label="Aanvragen" :value="(string) $d['total']" sub="in deze periode" />
        <x-kpi label="In de pipeline" :value="$eur($d['pipeline_value'])" sub="nog open" />
        <x-kpi label="Terugkerende klanten" :value="(string) $d['repeat_customers']" sub="meer dan een project" />
    </x-kpi-row>

    @if ($d['best_source'])
        <div class="mb-5 flex items-start gap-3 rounded-2xl border border-green-500/25 bg-green-500/5 p-4">
            <x-icon name="light-bulb" class="h-5 w-5 shrink-0 text-green-300" />
            <p class="text-sm text-slate-200">
                <b class="text-white">{{ $d['best_source']['label'] }}</b> converteert het best:
                {{ rtrim(rtrim(number_format($d['best_source']['conversion'], 1, ',', '.'), '0'), ',') }} procent van wat beslist is werd een opdracht,
                gemiddeld {{ $eur($d['best_source']['average']) }} per opdracht.
            </p>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        {{-- Aanvragen per maand --}}
        <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5 lg:col-span-2">
            <h2 class="mb-4 text-sm font-bold text-white">Aanvragen <span class="text-brand-500">per maand</span></h2>

            <div class="flex h-32 items-end gap-2">
                @foreach ($d['per_month'] as $month => $count)
                    <div class="flex flex-1 flex-col items-center gap-2">
                        <span class="text-[10px] font-semibold text-slate-400">{{ $count ?: '' }}</span>
                        <span class="w-full rounded-t bg-planning-500/70" style="height: {{ max(2, round($count / $maxMonth * 100)) }}%"></span>
                        <span class="text-[10px] text-slate-500">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->translatedFormat('M') }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        @foreach ([['sources', 'Per bron', 'De campagne waar de aanvraag aan hing'], ['channels', 'Per kanaal', 'Hoe de aanvraag binnenkwam'], ['services', 'Per dienst', 'Welk werk er gevraagd wordt']] as [$key, $title, $about])
            <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-1 text-sm font-bold text-white">{{ $title }}</h2>
                <p class="mb-4 text-xs text-slate-500">{{ $about }}</p>

                @if ($d[$key]->isEmpty())
                    <p class="text-sm text-slate-500">Nog niets vastgelegd.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-surface-700 text-[10px] font-bold tracking-wider text-slate-500 uppercase">
                                    <th class="pb-2 text-left">Naam</th>
                                    <th class="pb-2 text-right">Totaal</th>
                                    <th class="pb-2 text-right">Gewonnen</th>
                                    <th class="pb-2 text-right">Conversie</th>
                                    <th class="pb-2 text-right">Waarde</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($d[$key]->take(10) as $row)
                                    <tr class="border-b border-surface-700/60">
                                        <td class="py-2 text-slate-200">{{ $row['label'] }}</td>
                                        <td class="py-2 text-right text-slate-400">{{ $row['total'] }}</td>
                                        <td class="py-2 text-right text-slate-300">{{ $row['won'] }}</td>
                                        <td class="py-2 text-right">
                                            <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $row['conversion'] >= 40 ? 'bg-green-500/15 text-green-300' : ($row['conversion'] >= 20 ? 'bg-amber-500/15 text-amber-300' : 'bg-white/10 text-slate-400') }}">
                                                {{ rtrim(rtrim(number_format($row['conversion'], 1, ',', '.'), '0'), ',') }}%
                                            </span>
                                        </td>
                                        <td class="py-2 text-right font-semibold text-white">{{ $eur($row['value']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</x-layouts.app>
