@php
    $eur = fn (float $v) => 'EUR '.number_format($v, 0, ',', '.');
    $num = fn (float $v, int $d = 1) => rtrim(rtrim(number_format($v, $d, ',', '.'), '0'), ',');
    // Rood vanaf vijf procent over de begroting, oranje zodra het erboven komt.
    $pill = fn (?float $v) => $v === null
        ? 'bg-white/10 text-slate-400'
        : ($v > 5 ? 'bg-red-500/15 text-red-300' : ($v > 0 ? 'bg-amber-500/15 text-amber-300' : 'bg-green-500/15 text-green-300'));
@endphp

<x-layouts.app title="Nacalculatie">
    <x-page-header title="Nacalculatie" eyebrow="Geld" category="calc"
                   subtitle="Begroot tegenover werkelijk en verwacht. Werkelijk komt uit goedgekeurde werkbonnen en inkoopfacturen." />

    @if ($lessons->isNotEmpty())
        <div class="mb-5 rounded-2xl border border-amber-500/25 bg-amber-500/5 p-5">
            <h2 class="mb-1 text-sm font-bold text-white">Leer<span class="text-brand-500">punten</span></h2>
            <p class="mb-4 text-xs text-slate-400">
                Over projecten heen: waar de geboekte uren structureel afwijken van de begrote. Alleen rollen met
                minstens twee werkbonnen en een afwijking van acht procent of meer.
            </p>

            <div class="space-y-2">
                @foreach ($lessons as $lesson)
                    <div class="flex items-center gap-3 rounded-xl border border-surface-700 bg-surface-800 p-3">
                        <span class="min-w-0 flex-1">
                            <b class="block truncate text-sm font-semibold text-white">{{ $lesson['role'] }}</b>
                            <small class="block text-xs text-slate-400">
                                {{ $num($lesson['actual']) }} geboekt tegenover {{ $num($lesson['budgeted']) }} begrote uren,
                                over {{ $lesson['orders'] }} werkbonnen
                            </small>
                        </span>
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-bold {{ $pill($lesson['variance']) }}">
                            {{ $lesson['variance'] > 0 ? '+' : '' }}{{ $num($lesson['variance']) }}%
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($rows->isEmpty())
        <x-empty-state icon="calculator" category="calc" title="Nog geen nacalculatie mogelijk"
                       subtitle="Een project heeft een begroting nodig om tegen af te zetten." />
    @else
        <div class="space-y-4">
            @foreach ($rows as $row)
                @php
                    $r = $row['review'];
                    $p = $row['project'];
                @endphp

                <div x-data="{ open: false }" class="overflow-hidden rounded-2xl border border-surface-700 bg-surface-800">
                    <button @click="open = !open" class="flex w-full flex-wrap items-center gap-3 p-5 text-left transition hover:bg-white/5">
                        <span class="min-w-0 flex-1">
                            <b class="block truncate text-sm font-bold text-white">{{ $p->name }}</b>
                            <small class="block truncate text-xs text-slate-400">
                                {{ $p->customer?->name }}, {{ $r['budget']->number }},
                                voortgang {{ $r['progress'] }}%,
                                {{ $num($r['hours_actual']) }} van {{ $num($r['hours_budgeted']) }} uur geboekt
                            </small>
                        </span>

                        <span class="grid shrink-0 grid-cols-3 gap-4 text-right">
                            <span>
                                <span class="block text-[10px] font-bold tracking-wider text-slate-500 uppercase">Begroot</span>
                                <span class="block text-sm font-bold text-slate-300">{{ $eur($r['budgeted']) }}</span>
                            </span>
                            <span>
                                <span class="block text-[10px] font-bold tracking-wider text-slate-500 uppercase">Werkelijk</span>
                                <span class="block text-sm font-bold text-white">{{ $eur($r['actual']) }}</span>
                            </span>
                            <span>
                                <span class="block text-[10px] font-bold tracking-wider text-slate-500 uppercase">Verwacht</span>
                                <span class="block text-sm font-bold text-white">{{ $eur($r['expected']) }}</span>
                            </span>
                        </span>

                        <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-bold {{ $pill($r['variance']) }}">
                            {{ $r['variance'] > 0 ? '+' : '' }}{{ $num($r['variance']) }}%
                        </span>
                    </button>

                    <div x-show="open" x-cloak class="border-t border-surface-700 bg-surface-850 p-5">
                        <div class="overflow-x-auto">
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
                                    @foreach ($r['rows'] as $line)
                                        <tr class="border-b border-surface-700/60">
                                            <td class="py-2.5 font-semibold text-slate-200">{{ $line['name'] }}</td>
                                            <td class="py-2.5 text-right text-slate-400">{{ $eur($line['budgeted']) }}</td>
                                            <td class="py-2.5 text-right text-white">{{ $eur($line['actual']) }}</td>
                                            <td class="py-2.5 text-right font-semibold text-white">{{ $eur($line['expected']) }}</td>
                                            <td class="py-2.5 text-right">
                                                <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $pill($line['variance']) }}">
                                                    {{ $line['variance'] === null ? 'n.v.t.' : (($line['variance'] > 0 ? '+' : '').$num($line['variance']).'%') }}
                                                </span>
                                            </td>
                                            <td class="py-2.5 pl-4 text-xs text-slate-500">{{ $line['note'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 grid gap-4 border-t border-surface-700 pt-4 sm:grid-cols-4">
                            <div>
                                <p class="text-[11px] font-bold tracking-widest text-slate-500 uppercase">Aanneemsom</p>
                                <p class="text-base font-bold text-white">{{ $eur($r['value']) }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] font-bold tracking-widest text-slate-500 uppercase">Marge begroot</p>
                                <p class="text-base font-bold text-slate-300">{{ $num($r['margin_budgeted']) }}%</p>
                            </div>
                            <div>
                                <p class="text-[11px] font-bold tracking-widest text-slate-500 uppercase">Marge verwacht</p>
                                <p class="text-base font-bold {{ $r['margin_expected'] < $r['margin_budgeted'] - 2 ? 'text-red-400' : 'text-green-400' }}">
                                    {{ $num($r['margin_expected']) }}%
                                </p>
                            </div>
                            <div>
                                <p class="text-[11px] font-bold tracking-widest text-slate-500 uppercase">Urenfactor</p>
                                <p class="text-base font-bold text-white">{{ $num($r['efficiency'], 2) }}</p>
                                <p class="text-xs text-slate-500">boven één is meer uren dan begroot</p>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.app>
