@php
    $groet = now()->hour < 12 ? 'Goedemorgen' : (now()->hour < 18 ? 'Goedemiddag' : 'Goedenavond');
@endphp

{{-- Fotokop met begroeting (briefing hoofdstuk 6) --}}
<section class="relative mb-4 overflow-hidden rounded-2xl">
    {{-- Vaste bannerfoto. De linkerkant is al donker, dus een licht verloop houdt de tekst leesbaar. --}}
    <img src="{{ asset('images/dashboard-banner.jpg') }}" alt="" class="absolute inset-0 h-full w-full object-cover object-right">
    <div class="absolute inset-0 bg-gradient-to-r from-surface-950/85 via-surface-950/50 to-transparent"></div>

    <div class="relative px-6 py-10 lg:px-10 lg:py-14">
        <p class="text-[11px] font-bold tracking-widest text-slate-300 uppercase">{{ now()->translatedFormat('l j F Y') }}</p>
        <span class="mt-2 block h-1 w-12 rounded-full bg-brand-500"></span>
        <h1 class="mt-3 text-3xl font-bold text-white lg:text-5xl">
            {{ $groet }}, <span class="text-brand-500">{{ str(auth()->user()->name)->before(' ') }}</span>
        </h1>
        <p class="mt-3 max-w-xl text-sm text-slate-300">Van aanvraag tot nazorg, alles in een systeem.</p>
    </div>
</section>

{{-- Nova vat de inbox samen --}}
<section class="mb-4 flex flex-wrap items-center gap-4 rounded-2xl border border-brand-500/25 bg-brand-500/10 px-5 py-4">
    <x-nova-avatar class="h-10 w-10 shrink-0 ring-2 ring-brand-500" />

    <div class="min-w-0 flex-1" x-data="{
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
        <div x-show="loading && !briefing" class="max-w-2xl"><x-skeleton :lines="2" dark /></div>

        <p class="text-sm leading-relaxed text-slate-200" x-show="!briefing && !loading">
            @if ($aandachtTotaal > 0)
                Je inbox heeft <span class="font-bold text-white">{{ $attentionCount }} {{ $attentionCount === 1 ? 'punt' : 'punten' }}</span>.
                Vandaag {{ $aandachtTotaal === 1 ? 'vraagt 1 zaak' : "vragen {$aandachtTotaal} zaken" }} je aandacht.
            @else
                Alles loopt op schema. Geen openstaande acties voor vandaag.
            @endif
        </p>
        <p class="text-sm leading-relaxed whitespace-pre-line text-slate-200" x-show="briefing" x-text="briefing" x-cloak></p>
        <button x-show="briefing && !loading" x-cloak @click="load(true)"
                class="mt-1 text-xs font-semibold text-slate-400 transition hover:text-white">Briefing vernieuwen</button>
    </div>

    <a href="{{ route('attention.index') }}"
       class="flex shrink-0 items-center gap-1.5 rounded-full bg-brand-500 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600">
        Naar inbox <x-icon name="chevron-right" class="h-4 w-4" />
    </a>
</section>

@php
    // KPI-strip: de eerste kaart is navy en breder, dat is het getal waar het
    // die week om draait. De rest staat ernaast, allemaal aanklikbaar.
    $kpis = collect([
        [
            'label' => 'Werkvoorraad excl. btw',
            'value' => 'EUR '.number_format($stats['omzet_lopend'], 0, ',', '.'),
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

<x-kpi-row class="mb-4 {{ $kpis->count() === 5 ? 'xl:grid-cols-[1.5fr_repeat(4,minmax(0,1fr))]' : '' }}">
    @foreach ($kpis as $kpi)
        <x-kpi :label="$kpi['label']" :value="(string) $kpi['value']" :sub="$kpi['sub']"
               :dark="$kpi['dark'] ?? false" :alert="$kpi['alert'] ?? false" :href="$kpi['url']" />
    @endforeach
</x-kpi-row>

{{-- MT-signalen: de uitzonderingen die het MT wil zien, ingeklapt --}}
@if ($aandachtItems->isNotEmpty())
    <section x-data="{ open: false }" class="mb-6 rounded-2xl border border-surface-700 bg-surface-800 px-5 py-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm font-bold text-white">MT<span class="text-brand-500">-signalen</span>
                <span class="ml-2 text-xs font-medium text-slate-400">{{ $attentionCount }} {{ $attentionCount === 1 ? 'signaal' : 'signalen' }} op de radar</span>
            </p>
            <button type="button" @click="open = !open"
                    class="rounded-full border border-surface-600 px-4 py-1.5 text-xs font-semibold text-slate-300 transition hover:border-surface-600 hover:text-white">
                <span x-text="open ? 'Verberg signalen' : 'Toon signalen'"></span>
            </button>
        </div>

        <div x-show="open" x-cloak class="mt-4 space-y-2 border-t border-surface-700 pt-4">
            @foreach ($aandachtItems as $item)
                <a href="{{ $item['url'] }}" class="flex items-center gap-3 rounded-xl border border-surface-700 border-l-4 bg-surface-850 p-3 transition hover:border-surface-600 {{ $item['severity'] === 'rood' ? 'border-l-red-500' : 'border-l-amber-400' }}">
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-white">{{ $item['title'] }}</span>
                        <span class="block truncate text-xs text-slate-400">{{ $item['label'] }}</span>
                    </span>
                    <x-signal-dot :color="$item['severity'] === 'rood' ? 'red' : 'amber'" />
                </a>
            @endforeach
        </div>
    </section>
@endif
