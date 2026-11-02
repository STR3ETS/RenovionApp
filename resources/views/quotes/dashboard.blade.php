@php
    $eur = fn (float $v) => 'EUR '.number_format($v, 0, ',', '.');
    $pct = fn (?float $v) => $v === null ? 'n.v.t.' : rtrim(rtrim(number_format($v, 1, ',', '.'), '0'), ',').'%';
    $d = $data;
    $states = [
        'open' => ['Open', 'bg-lead-500/15 text-lead-300'],
        'getekend' => ['Getekend', 'bg-green-500/15 text-green-300'],
        'afgewezen' => ['Afgewezen', 'bg-red-500/15 text-red-300'],
        'verlopen' => ['Verlopen', 'bg-white/10 text-slate-400'],
    ];
@endphp

<x-layouts.app title="Offertedashboard">
    <x-page-header title="Offertedashboard" eyebrow="Calculatie en offertes" category="offerte"
                   subtitle="Wat is verstuurd, wat is geopend, wat is getekend, en wat kan er nog komen.">
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <select name="periode" onchange="this.form.submit()"
                    class="rounded-full border-surface-700 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                @foreach ($periods as $key => $label)
                    <option value="{{ $key }}" @selected($d['period'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="verkoper" onchange="this.form.submit()"
                    class="rounded-full border-surface-700 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                <option value="">Alle verkopers</option>
                @foreach ($sellers as $seller)
                    <option value="{{ $seller->id }}" @selected($d['seller'] === $seller->id)>{{ $seller->name }}</option>
                @endforeach
            </select>
        </form>
    </x-page-header>

    <section data-motion-fan class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-3 xl:grid-cols-6">
        @foreach ([
            ['Verstuurd', (string) $d['count'], $eur($d['value']), 'bg-brand-500', 'text-brand-400'],
            ['Getekend', (string) $d['won']->count(), $eur($d['won_value']), 'bg-green-500', 'text-green-400'],
            ['Open', (string) $d['open']->count(), $eur($d['open_value']), 'bg-lead-500', 'text-lead-400'],
            ['Gewogen open', $eur($d['weighted']), 'naar scoringskans', 'bg-calc-500', 'text-calc-400'],
            ['Hitrate op aantal', $pct($d['hit_rate']), 'op geld '.$pct($d['hit_rate_value']), 'bg-project-500', 'text-project-400'],
            ['Wordt geopend', $pct($d['open_rate']), $d['days_to_open'] === null
                ? 'nog geen meting'
                : 'gemiddeld na '.rtrim(rtrim(number_format($d['days_to_open'], 1, ',', '.'), '0'), ',').' dagen', 'bg-planning-500', 'text-planning-400'],
        ] as [$label, $value, $sub, $accent, $labelClass])
            <div class="motion-lift overflow-hidden rounded-2xl border border-surface-700 bg-surface-800">
                <span class="block h-[3px] w-full {{ $accent }}"></span>
                <div class="p-4">
                    <p class="text-[11px] font-bold tracking-widest uppercase {{ $labelClass }}">{{ $label }}</p>
                    <p data-count class="mt-1 text-lg font-bold text-white">{{ $value }}</p>
                    <p class="mt-0.5 text-xs text-slate-400">{{ $sub }}</p>
                </div>
            </div>
        @endforeach
    </section>

    <div class="grid gap-4 xl:grid-cols-3">
        <div class="xl:col-span-2">
            <div class="overflow-hidden rounded-2xl border border-surface-700 bg-surface-800">
                <div class="border-b border-surface-700 p-5">
                    <h2 class="text-sm font-bold text-white">Alle <span class="text-brand-500">offertes</span></h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        De scoringskans komt uit gedrag: vragen gesteld is zestig procent, twee keer gekeken vijfenveertig,
                        een keer gekeken vijfendertig, nooit geopend vijftien.
                    </p>
                </div>

                @if ($d['rows']->isEmpty())
                    <div class="p-5">
                        <x-empty-state icon="document-text" category="offerte" title="Nog geen verstuurde offertes"
                                       subtitle="Zodra je een offerte verstuurt, verschijnt hij hier met wat de klant ermee doet." />
                    </div>
                @else
                    <div class="divide-y divide-surface-700">
                        @foreach ($d['rows'] as $row)
                            @php
                                $quote = $row['quote'];
                                [$stateLabel, $stateClass] = $states[$row['state']];
                            @endphp
                            <a href="{{ route('quotes.show', $quote) }}" class="flex flex-wrap items-center gap-3 p-4 transition hover:bg-white/5">
                                <span class="min-w-0 flex-1">
                                    <span class="flex flex-wrap items-center gap-2">
                                        <b class="truncate text-sm font-semibold text-white">{{ $quote->customer->name }}</b>
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $stateClass }}">{{ $stateLabel }}</span>
                                        @if ($quote->questions_count > 0)
                                            <span class="rounded-full bg-calc-500/15 px-2 py-0.5 text-[10px] font-bold text-calc-300">
                                                {{ $quote->questions_count }} {{ $quote->questions_count === 1 ? 'vraag' : 'vragen' }}
                                            </span>
                                        @endif
                                    </span>
                                    <small class="mt-0.5 block truncate text-xs text-slate-400">
                                        {{ $quote->number }}, verstuurd {{ $quote->sent_at->translatedFormat('j M') }},
                                        @if ($quote->views_count > 0)
                                            {{ $quote->views_count }} keer geopend, laatst {{ $quote->views->first()->viewed_at->diffForHumans() }}
                                        @else
                                            nog niet geopend
                                        @endif
                                    </small>
                                </span>

                                <span class="shrink-0 text-right">
                                    <b class="block text-sm font-bold text-white">{{ $eur((float) $quote->total) }}</b>
                                    @if ($row['state'] === 'open')
                                        <small class="block text-xs text-slate-500">
                                            kans {{ round($row['chance'] * 100) }}%, gewogen {{ $eur((float) $quote->total * $row['chance']) }}
                                        </small>
                                    @elseif ($row['days_to_sign'] !== null)
                                        <small class="block text-xs text-green-400">getekend na {{ $row['days_to_sign'] }} dagen</small>
                                    @endif
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="space-y-4">
            <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-4 text-sm font-bold text-white">Door<span class="text-brand-500">looptijd</span></h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-400">Tot eerste opening</dt>
                        <dd class="font-bold text-white">
                            {{ $d['days_to_open'] === null ? 'geen meting' : rtrim(rtrim(number_format($d['days_to_open'], 1, ',', '.'), '0'), ',').' dagen' }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-400">Tot ondertekening</dt>
                        <dd class="font-bold text-white">
                            {{ $d['days_to_sign'] === null ? 'geen meting' : rtrim(rtrim(number_format($d['days_to_sign'], 1, ',', '.'), '0'), ',').' dagen' }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-400">Gemiddelde offerte</dt>
                        <dd class="font-bold text-white">{{ $eur($d['average_value']) }}</dd>
                    </div>
                </dl>
            </div>

            @if ($d['lost_reasons']->isNotEmpty())
                <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                    <h2 class="mb-1 text-sm font-bold text-white">Waarom <span class="text-brand-500">verloren</span></h2>
                    <p class="mb-4 text-xs text-slate-500">{{ $d['lost']->count() }} afgewezen, {{ $eur($d['lost_value']) }} aan waarde.</p>

                    <div class="space-y-2">
                        @php($hoogste = max(1, $d['lost_reasons']->max()))
                        @foreach ($d['lost_reasons'] as $reden => $aantal)
                            <div>
                                <div class="flex items-center justify-between gap-2 text-sm">
                                    <span class="min-w-0 truncate text-slate-300">{{ $reden }}</span>
                                    <span class="shrink-0 font-semibold text-white">{{ $aantal }}</span>
                                </div>
                                <span class="mt-1 block h-1 overflow-hidden rounded-full bg-surface-700">
                                    <span class="block h-full rounded-full bg-red-500/70" style="width: {{ round($aantal / $hoogste * 100) }}%"></span>
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
