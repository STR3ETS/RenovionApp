@php
    $groet = now()->hour < 12 ? 'Goedemorgen' : (now()->hour < 18 ? 'Goedemiddag' : 'Goedenavond');
@endphp

{{-- Merkband met begroeting: navy met de bannerfoto rechts erachter --}}
<section class="band on-navy mb-[22px]">
    <img src="{{ asset('images/dashboard-banner.jpg') }}" alt="" aria-hidden="true" class="bimg">
    <div class="grid gap-1.5">
        <span class="eyebrow">{{ now()->translatedFormat('l j F Y') }}</span>
        <h1>{{ $groet }}, <em>{{ str(auth()->user()->name)->before(' ') }}</em></h1>
        <div class="rule"></div>
    </div>
    <p>Van aanvraag tot nazorg, alles in een systeem.</p>
</section>

{{-- De schermen die je zelf het meest opent, hooguit vijf (ux.js vult de lijst) --}}
<section data-veelgebruikt hidden class="ux-quick mb-[22px]" aria-label="Snel naar">
    <span>Snel naar</span>
    <div data-veelgebruikt-lijst class="contents"></div>
</section>

@if ($onboarding)
    {{-- Aan de slag: uitleg en checklist, tot de gebruiker hem zelf weghaalt --}}
    <section class="ac-welcome mb-[22px]">
        <span class="ac-pi"><x-icon name="academic-cap" /></span>
        <div>
            <b>{{ $onboarding['complete'] ? 'Aan de slag: alles staat klaar om te beginnen' : 'Aan de slag: '.$onboarding['open'].' '.($onboarding['open'] === 1 ? 'stap' : 'stappen').' nog te doen' }}</b>
            <small>Lees hoe het systeem werkt en loop de checklist door. {{ $onboarding['done'] }} van {{ $onboarding['total'] }} stappen zijn klaar.</small>
        </div>
        <a href="{{ route('onboarding') }}" class="btn pri sm"><x-icon name="play" />Aan de slag</a>
        @if (Route::has('academy.index'))
            <a href="{{ route('academy.index') }}" class="btn sm">Academy</a>
        @endif
    </section>
@endif

{{-- Nova vat de inbox samen --}}
<section class="novaline mb-[22px]">
    <x-nova-avatar class="h-9 w-9 shrink-0 ring-2 ring-brand-500 ring-offset-2 ring-offset-surface-800" />

    <div class="tekst" x-data="{
            briefing: null,
            loading: false,
            async load(refresh = false) {
                this.loading = true;
                try {
                    const response = await fetch('{{ route('nova.briefing') }}' + (refresh ? '?refresh=1' : ''), { headers: { 'Accept': 'application/json' } });
                    this.briefing = (await response.json()).text;
                } catch (error) { /* de vaste tekst blijft staan */ }
                this.loading = false;
            },
         }" x-init="load()">
        <div x-show="loading && !briefing" class="max-w-2xl"><x-skeleton :lines="2" /></div>

        <p x-show="!briefing && !loading">
            @if ($aandachtTotaal > 0)
                Je inbox heeft <b>{{ $attentionCount }} {{ $attentionCount === 1 ? 'punt' : 'punten' }}</b>.
                Vandaag {{ $aandachtTotaal === 1 ? 'vraagt 1 zaak' : "vragen {$aandachtTotaal} zaken" }} je aandacht.
            @else
                Alles loopt op schema. Geen openstaande acties voor vandaag.
            @endif
        </p>
        <p class="whitespace-pre-line" x-show="briefing" x-text="briefing" x-cloak></p>
        <button x-show="briefing && !loading" x-cloak @click="load(true)"
                class="mt-1 text-xs font-semibold text-muted transition hover:text-ink">Briefing vernieuwen</button>
    </div>

    <a href="{{ route('attention.index') }}" class="btn sm soft">Naar inbox <x-icon name="chevron-right" /></a>
</section>

@php
    // KPI-strip: de eerste kaart is navy en breder, dat is het getal waar het
    // die week om draait. De rest staat ernaast, allemaal aanklikbaar.
    $kpis = collect([
        [
            'label' => 'Werkvoorraad excl. btw',
            'value' => '€ '.number_format($stats['omzet_lopend'], 0, ',', '.'),
            'sub' => $stats['actieve_projecten'].' '.($stats['actieve_projecten'] === 1 ? 'project' : 'projecten').' in opdracht',
            'dark' => true,
            'url' => route('projects.index'),
        ],
        [
            'label' => 'Lopende projecten',
            'value' => $stats['actieve_projecten'],
            'sub' => $stats['projecten_aandacht'] > 0
                ? $stats['projecten_aandacht'].' '.($stats['projecten_aandacht'] === 1 ? 'vraagt aandacht' : 'vragen aandacht')
                : 'alles op schema',
            'alert' => $stats['projecten_aandacht'] > 0,
            'url' => route('portfolio'),
        ],
        $quotesEnabled ? [
            'label' => 'Open offertes',
            'value' => $stats['open_offertes'],
            'sub' => 'wachten op de klant',
            'url' => route('quotes.index'),
        ] : null,
        [
            'label' => 'Nieuwe aanvragen',
            'value' => $stats['nieuwe_aanvragen'],
            'sub' => $stats['open_aanvragen'].' open in de pipeline',
            'url' => route('leads.index'),
        ],
        [
            'label' => 'Taken deze week',
            'value' => $stats['taken_deze_week'],
            'sub' => $stats['taken_te_laat'] > 0 ? $stats['taken_te_laat'].' te laat' : 'niets te laat',
            'alert' => $stats['taken_te_laat'] > 0,
            'url' => route('tasks.index'),
        ],
    ])->filter()->values();
@endphp

<x-kpi-row class="{{ $kpis->count() === 5 ? 'five' : '' }}">
    @foreach ($kpis as $kpi)
        <x-kpi :label="$kpi['label']" :value="(string) $kpi['value']" :sub="$kpi['sub']"
               :dark="$kpi['dark'] ?? false" :alert="$kpi['alert'] ?? false" :href="$kpi['url']" />
    @endforeach
</x-kpi-row>

{{-- MT-signalen: de uitzonderingen die het MT wil zien, ingeklapt --}}
@if ($aandachtItems->isNotEmpty())
    <section x-data="{ open: false }" class="card mb-[22px]">
        <div class="card-h" :class="open || 'border-b-0'">
            <h3>MT-<em>signalen</em></h3>
            <span class="ml-auto text-[12.5px] text-muted">{{ $attentionCount }} {{ $attentionCount === 1 ? 'signaal' : 'signalen' }} op de radar</span>
            <button type="button" @click="open = !open" class="btn xs">
                <span x-text="open ? 'Verberg signalen' : 'Toon signalen'">Toon signalen</span>
            </button>
        </div>

        <div x-show="open" x-cloak class="card-b" style="padding-block: 4px">
            @foreach ($aandachtItems as $item)
                <a href="{{ $item['url'] }}" class="li">
                    <span class="dotk {{ $item['severity'] === 'rood' ? 'bad' : 'or' }}"></span>
                    <span class="tx">
                        <b class="truncate">{{ $item['title'] }}</b>
                        <small class="truncate">{{ $item['label'] }}</small>
                    </span>
                    <x-icon name="chevron-right" class="h-4 w-4 text-faint" />
                </a>
            @endforeach
        </div>
    </section>
@endif
