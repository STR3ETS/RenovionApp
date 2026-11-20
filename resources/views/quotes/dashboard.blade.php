@php
    $d = $data;
    $eur = fn (float $v) => '€ '.number_format($v, 0, ',', '.');
    $pct = fn (?float $v) => number_format((float) $v, 0, ',', '.').'%';
    $getal = fn (?float $v) => $v === null ? 'geen meting' : rtrim(rtrim(number_format($v, 1, ',', '.'), '0'), ',');

    // Een offerte heeft geen eigen pagina: hij staat in het dossier.
    $dossier = fn ($quote) => $quote->project
        ? route('projects.show', [$quote->project, 'tab' => 'offerte'])
        : route('quotes.show', $quote);

    $query = fn (array $extra) => route('quote-dashboard', array_filter(array_merge([
        'periode' => $d['period'], 'verkoper' => $d['seller'], 'type' => $d['type'],
    ], $extra)));

    $pil = function (array $row) {
        $quote = $row['quote'];

        return match ($row['state']) {
            'getekend' => ['ok', 'Getekend'],
            'afgewezen' => ['bad', 'Afgewezen'],
            'verlopen' => ['mut', 'Verlopen'],
            default => $quote->views_count > 0 ? ['blue', 'Geopend '.$quote->views_count.'×'] : ['warn', 'Nog niet geopend'],
        };
    };

    // De staafgrafiek: een ronde bovengrens met vier stappen.
    $top = max(1.0, (float) collect($d['months'])->max('sent'));
    $macht = 10 ** floor(log10($top / 4));
    $stap = collect([1, 2, 2.5, 5, 10])->map(fn ($f) => $f * $macht)->first(fn ($s) => $s * 4 >= $top);
    $plafond = $stap * 4;
    $as = fn (float $v) => match (true) {
        $v >= 1_000_000 => '€ '.rtrim(rtrim(number_format($v / 1_000_000, 1, ',', '.'), '0'), ',').' mln',
        $v >= 1_000 => '€ '.rtrim(rtrim(number_format($v / 1_000, 1, ',', '.'), '0'), ',').'k',
        default => '€ '.number_format($v, 0, ',', '.'),
    };

    $stappen = [
        ['Verzonden', $d['count'], $d['value'], '--ch1'],
        ['Geopend door klant', $d['opened']->count(), $d['opened_value'], '--ch2'],
        ['Getekend', $d['won']->count(), $d['won_value'], '--ch3'],
    ];

    $open = $d['open']->sortBy([
        fn ($a, $b) => $b['quote']->views_count <=> $a['quote']->views_count,
        fn ($a, $b) => $a['quote']->sent_at <=> $b['quote']->sent_at,
    ])->values();

    $nietGewonnen = $d['lost_value'] + round($d['expired']->sum(fn ($row) => (float) $row['quote']->total), 2);
@endphp

<x-layouts.app title="Offertedashboard">
    <x-page-header title="Offertedashboard"
                   subtitle="Hoeveel offertes er uitstaan, hoeveel de klant heeft geopend, wat er getekend is en wat dat oplevert. Bedragen incl. btw." />

    {{-- Periode als pilbalk, verkoper en soort werk als keuzelijst --}}
    <form method="GET" class="mb-[22px] flex flex-wrap items-center gap-2">
        <input type="hidden" name="periode" value="{{ $d['period'] }}">
        <div class="tabs">
            @foreach ($periods as $key => $label)
                <a href="{{ $query(['periode' => $key]) }}" class="tab {{ $d['period'] === $key ? 'on' : '' }}" @if ($d['period'] === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </div>
        <select name="verkoper" onchange="this.form.submit()" aria-label="Verkoper" class="h-9 w-auto py-0 pr-9 text-sm font-semibold">
            <option value="">Alle verkopers</option>
            @foreach ($sellers as $seller)
                <option value="{{ $seller->id }}" @selected($d['seller'] === $seller->id)>{{ $seller->name }}</option>
            @endforeach
        </select>
        <select name="type" onchange="this.form.submit()" aria-label="Soort werk" class="h-9 w-auto py-0 pr-9 text-sm font-semibold">
            <option value="">Alle typen</option>
            @foreach ($d['types'] as $type)
                <option value="{{ $type }}" @selected($d['type'] === $type)>{{ $type }}</option>
            @endforeach
        </select>
    </form>

    <div class="alert info mb-[22px]">
        <x-icon name="chart-bar" />
        <div class="min-w-0 flex-1">
            <b>{{ $d['count'] }} verzonden {{ $d['count'] === 1 ? 'offerte' : 'offertes' }}</b> in deze selectie.
            Geopend wordt gemeten zodra de klant de offerte via de klantlink bekijkt. Zonder beslissing telt een offerte na {{ \App\Services\QuoteDashboard::EXPIRES_AFTER_DAYS }} dagen als verlopen.
        </div>
    </div>

    <x-kpi-row>
        <x-kpi dark label="Opdrachtwaarde getekend" :value="$eur($d['won_value'])"
               :sub="$d['won']->count().' '.($d['won']->count() === 1 ? 'opdracht' : 'opdrachten').', gem. '.$eur($d['won']->count() ? $d['won_value'] / $d['won']->count() : 0)" />
        <x-kpi label="Offertes verzonden" :value="(string) $d['count']"
               :sub="$eur($d['value']).' totaal, gem. '.$eur($d['average_value'])" />
        <x-kpi label="Geopend door klant" :value="$pct($d['open_rate'])"
               :sub="$d['opened']->count().' van '.$d['count'].($d['days_to_open'] !== null ? ', gem. na '.$getal($d['days_to_open']).' dag' : '')" />
        <x-kpi label="Scoringskans" :value="$pct($d['hit_rate'])"
               :sub="'op aantal, '.$pct($d['hit_rate_value']).' op waarde'" />
    </x-kpi-row>

    <x-kpi-row :wide="false">
        <x-kpi style="--kc: var(--ch1)" label="Open offertes" :value="(string) $d['open']->count()" :sub="$eur($d['open_value']).' uitstaand'" />
        <x-kpi style="--kc: #7c5cd6" label="Gewogen pipeline" :value="$eur($d['weighted'])" sub="kans naar klantgedrag" />
        <x-kpi style="--kc: #0e8fc2" label="Doorlooptijd tot tekenen"
               :value="$d['days_to_sign'] !== null ? number_format($d['days_to_sign'], 0, ',', '.').' dagen' : 'geen meting'" sub="gemiddeld vanaf verzending" />
        <x-kpi style="--kc: #c2506d" label="Afgewezen / verlopen" :value="$d['lost']->count().' / '.$d['expired']->count()" :sub="$eur($nietGewonnen).' niet gewonnen'" />
    </x-kpi-row>

    <div class="mb-[22px] grid gap-[14px] lg:grid-cols-[minmax(0,1.7fr)_minmax(0,1fr)]">
        {{-- Verzonden en gewonnen per maand --}}
        <section class="card">
            <div class="card-h">
                <h3>Verzonden en <em>gewonnen</em> per maand</h3>
                <span class="text-xs text-muted">waarde incl. btw</span>
            </div>
            <div class="card-b">
                <div class="legenda">
                    <span><i style="background: var(--ch1)"></i>Verzonden</span>
                    <span><i style="background: var(--ch3)"></i>Getekend</span>
                </div>
                <div class="bars" role="img" aria-label="Offertewaarde per maand, verzonden en getekend">
                    <div class="yas">
                        @foreach ([4, 3, 2, 1, 0] as $n)
                            <span>{{ $as($stap * $n) }}</span>
                        @endforeach
                    </div>
                    <div class="veld">
                        @foreach (range(1, 5) as $lijn)
                            <hr>
                        @endforeach
                        <div class="kolommen">
                            @foreach ($d['months'] as $month)
                                <div class="kolom" title="{{ $month['label'] }}: {{ $eur($month['sent']) }} verzonden, {{ $eur($month['won']) }} getekend">
                                    <i class="motion-bar" style="height: {{ round($month['sent'] / $plafond * 100, 2) }}%; background: var(--ch1); transform-origin: bottom"></i>
                                    <i class="motion-bar" style="height: {{ round($month['won'] / $plafond * 100, 2) }}%; background: var(--ch3); transform-origin: bottom"></i>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="xas">
                        @foreach ($d['months'] as $month)
                            <span>{{ $month['label'] }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        {{-- Trechter en waarom niet gewonnen --}}
        <section class="card">
            <div class="card-h"><h3>Trechter</h3></div>
            <div class="card-b grid gap-2.5">
                @foreach ($stappen as $i => [$label, $aantal, $waarde, $kleur])
                    <div class="odf">
                        <div class="flex items-center justify-between gap-2"><b class="font-semibold">{{ $label }}</b><span class="tabular-nums">{{ $aantal }}, {{ $eur($waarde) }}</span></div>
                        <div class="odbar"><i style="width: {{ $d['count'] ? round($aantal / $d['count'] * 100, 1) : 0 }}%; background: var({{ $kleur }})"></i></div>
                        @if ($i > 0)
                            <small class="text-xs text-muted">{{ $pct($stappen[$i - 1][1] ? $aantal / $stappen[$i - 1][1] * 100 : 0) }} van vorige stap</small>
                        @endif
                    </div>
                @endforeach

                <span class="lbl mt-1.5">Waarom niet gewonnen</span>
                @forelse ($d['lost_reasons'] as $reden => $aantal)
                    <div class="flex items-center justify-between gap-2 text-[13px]"><span>{{ $reden }}</span><b class="tabular-nums">{{ $aantal }}</b></div>
                @empty
                    <span class="text-[13px] text-muted">Nog niets afgewezen in deze selectie.</span>
                @endforelse
            </div>
        </section>
    </div>

    {{-- Open offertes: wat de klant ermee doet, en wat wij nu doen --}}
    <section class="card mb-[22px]">
        <div class="card-h">
            <h3>Open <em>offertes</em></h3>
            <span class="pill blue">{{ $open->count() }}</span>
        </div>
        <div class="tw">
            <table class="t">
                <thead>
                    <tr><th>Offerte</th><th>Klant, project</th><th>Verzonden</th><th>Klantgedrag</th><th class="r">Waarde</th><th class="r">Kans</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($open as $row)
                        @php
                            $quote = $row['quote'];
                            [$toon, $tekst] = $pil($row);
                            $laatst = $quote->views->max('viewed_at');
                        @endphp
                        <tr>
                            <td><b>{{ $quote->number }}</b></td>
                            <td>
                                {{ $quote->customer->name }}
                                <span class="sub">{{ collect([$quote->project?->name ?? (new \App\Services\QuoteDashboard)->type($quote), $quote->lead?->assignee?->name])->filter()->implode(', ') }}</span>
                            </td>
                            <td class="tabular-nums">
                                {{ $quote->sent_at->translatedFormat('j M') }}
                                <span class="sub">{{ (int) $quote->sent_at->diffInDays(now()) }} dagen geleden</span>
                            </td>
                            <td>
                                <span class="pill {{ $toon }}">{{ $tekst }}</span>
                                @if ($laatst)
                                    <span class="sub">laatst {{ $laatst->translatedFormat('j M H:i') }}</span>
                                @endif
                                @if ($quote->questions_count > 0)
                                    <span class="sub">{{ $quote->questions_count }} {{ $quote->questions_count === 1 ? 'vraag' : 'vragen' }} gesteld</span>
                                @endif
                            </td>
                            <td class="r">{{ $eur((float) $quote->total) }}</td>
                            <td class="r">{{ $pct($row['chance'] * 100) }}</td>
                            <td class="r whitespace-nowrap">
                                <a href="{{ $dossier($quote) }}" class="btn xs">Dossier</a>
                                <button type="button" x-data class="btn xs pri"
                                        @click="$dispatch('nova-ask', @js('Maak een opvolging voor offerte '.$quote->number.' van '.$quote->customer->name))">Opvolgen</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-8 text-center text-muted">Geen open offertes in deze selectie.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="mb-[22px] grid gap-[14px] xl:grid-cols-2">
        <section class="card">
            <div class="card-h"><h3>Per <em>projecttype</em></h3></div>
            <div class="tw">
                <table class="t">
                    <thead><tr><th>Type</th><th class="r">Verzonden</th><th class="r">Getekend</th><th class="r">Opdrachtwaarde</th><th class="r">Scoringskans</th></tr></thead>
                    <tbody>
                        @forelse ($d['by_type'] as $groep)
                            <tr><td><b>{{ $groep['name'] }}</b></td><td class="r">{{ $groep['count'] }}</td><td class="r">{{ $groep['won'] }}</td><td class="r">{{ $eur($groep['won_value']) }}</td><td class="r">{{ $pct($groep['hit_rate']) }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-muted">Nog geen offertes verzonden.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <div class="card-h"><h3>Per <em>verkoper</em></h3></div>
            <div class="tw">
                <table class="t">
                    <thead><tr><th>Verkoper</th><th class="r">Verzonden</th><th class="r">Opdrachtwaarde</th><th class="r">Scoringskans</th><th class="r">Doorlooptijd</th></tr></thead>
                    <tbody>
                        @forelse ($d['by_seller'] as $groep)
                            <tr><td><b>{{ $groep['name'] }}</b></td><td class="r">{{ $groep['count'] }}</td><td class="r">{{ $eur($groep['won_value']) }}</td><td class="r">{{ $pct($groep['hit_rate']) }}</td><td class="r">{{ $groep['days_to_sign'] !== null ? number_format($groep['days_to_sign'], 0, ',', '.').' d' : 'geen meting' }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-muted">Nog geen offertes verzonden.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    {{-- Alles in de selectie: een rij opent het dossier --}}
    <section class="card" x-data="{ alles: false }">
        <div class="card-h">
            <h3>Alle offertes in <em>selectie</em></h3>
            <span class="pill mut">{{ $d['count'] }}</span>
        </div>
        <div class="tw">
            <table class="t">
                <thead><tr><th>Offerte</th><th>Klant</th><th>Type</th><th>Verzonden</th><th>Geopend</th><th>Status</th><th class="r">Waarde</th></tr></thead>
                <tbody>
                    @forelse ($d['rows'] as $i => $row)
                        @php
                            $quote = $row['quote'];
                            [$toon, $tekst] = $pil($row);
                            $eerst = $quote->views->min('viewed_at');
                        @endphp
                        <tr class="click" onclick="window.location = @js($dossier($quote))" @if ($i >= 12) x-show="alles" x-cloak @endif>
                            <td><b><a href="{{ $dossier($quote) }}">{{ $quote->number }}</a></b></td>
                            <td>{{ $quote->customer->name }}</td>
                            <td>{{ (new \App\Services\QuoteDashboard)->type($quote) }}</td>
                            <td class="tabular-nums">{{ $quote->sent_at->translatedFormat('j M') }}</td>
                            <td class="tabular-nums">
                                @if ($eerst)
                                    {{ $eerst->translatedFormat('j M') }} <span class="text-[11.5px] text-muted">({{ $quote->views_count }}×)</span>
                                @else
                                    <span class="text-muted">nog niet</span>
                                @endif
                            </td>
                            <td>
                                <span class="pill {{ $toon }}">{{ $tekst }}</span>
                                @if ($row['state'] === 'afgewezen' && $quote->lead?->lost_reason)
                                    <span class="sub">{{ $quote->lead->lost_reason }}</span>
                                @endif
                            </td>
                            <td class="r">{{ $eur((float) $quote->total) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-8 text-center text-muted">In deze periode is nog geen offerte verzonden.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($d['count'] > 12)
            <div class="border-t border-line2 px-5 py-4 text-center">
                <button type="button" class="btn sm" @click="alles = ! alles" x-text="alles ? 'Toon minder' : 'Toon alle {{ $d['count'] }}'">Toon alle {{ $d['count'] }}</button>
            </div>
        @endif
    </section>
</x-layouts.app>
