@props([
    'title',
    'accent' => null,
    'subtitle' => null,
    'image' => null,
    // Het label boven de kop. Standaard de sectie waar je bent ("Projecten",
    // "Geld"); op detailschermen geef je het recordnummer mee.
    'eyebrow' => null,
    'category' => null,
])

@php
    // Een deel van de kop krijgt de sectiekleur, net als in het prototype:
    // "Projectdossiers", "Facturatie", "Aan de slag". Bij een bekend woord
    // staat hier waar de kleur begint; een korte kop van één bekend woord
    // kleurt helemaal. Zeg je het zelf (accent), dan wint dat.
    $splits = [
        'Automations' => 'mations', 'Calculaties' => 'laties', 'Documenten' => 'menten', 'Facturatie' => 'ratie',
        'Financieel' => 'cieel', 'Goedkeuringen' => 'keuringen', 'Inkoop' => 'koop', 'Instellingen' => 'lingen',
        'Mailbuilder' => 'builder', 'Marketing' => 'ting', 'Nacalculatie' => 'calculatie', 'Offertedashboard' => 'dashboard',
        'Onderaannemers' => 'aannemers', 'Planning' => 'ning', 'Prijslijst' => 'lijst', 'Projectdossiers' => 'dossiers',
        'Projectportfolio' => 'portfolio', 'Portfolio' => 'folio', 'Rapportages' => 'tages', 'Teambeheer' => 'beheer',
        'Urenstaten' => 'staten', 'Werkbonnen' => 'bonnen', 'Prijsaanvragen' => 'aanvragen', 'Begrotingen' => 'tingen',
        'Projecten' => 'ten', 'Aanvraagmail' => 'mail', 'Uitvoeringen' => 'voeringen', 'Opvolging' => 'volging',
        'Teamchat' => 'chat', 'Stijlgids' => 'gids', 'Offertetool' => 'tool', 'Klantportaal' => 'portaal',
        'Aanvragen' => 'vragen', 'Klanten' => 'ten', 'Zoekresultaten' => 'resultaten',
    ];
    $heel = ['Inbox', 'Agenda', 'Offertes', 'Aandacht', 'Audit', 'Taken', 'Academy', 'Zoeken'];

    $kop = trim($title);
    $woorden = preg_split('/\s+/', $kop) ?: [];
    $spatie = true;

    if ($accent !== null) {
        $kleur = $accent;
    } elseif (count($woorden) === 1 && isset($splits[$kop])) {
        $kleur = $splits[$kop];
        $spatie = false;
    } elseif (count($woorden) === 1 && in_array($kop, $heel, true)) {
        $kleur = $kop;
    } elseif (count($woorden) > 1 && count($woorden) <= 3) {
        $kleur = end($woorden);
    } else {
        $kleur = null;
    }

    $gewoon = $kleur !== null && str_ends_with($kop, $kleur)
        ? mb_substr($kop, 0, mb_strlen($kop) - mb_strlen($kleur))
        : $kop;

    if ($kleur !== null && ! str_ends_with($kop, $kleur)) {
        $kleur = null;
    }

    $sectie = auth()->check() ? \App\Support\Navigation::currentSection(auth()->user()) : null;
    $label = $eyebrow ?? ($sectie['label'] ?? null);
@endphp

<div class="ph mb-[22px]">
    <div class="flex min-w-0 items-end gap-3">
        @if ($image)
            <img src="{{ $image }}" alt="" class="h-12 w-12 shrink-0 rounded-xl object-cover ring-1 ring-surface-700">
        @endif

        <div class="flex min-w-0 flex-col items-start">
            @if ($label)
                <span class="sec-eye">@if ($sectie)<x-icon :name="$sectie['icon']" />@endif{{ $label }}</span>
            @endif
            <h1>{{ $gewoon }}@if ($kleur)<em>{{ $kleur }}</em>@endif</h1>
            @if ($subtitle)
                <p>{{ $subtitle }}</p>
            @endif
        </div>
    </div>

    @if (trim($slot))
        <div class="acts">
            {{ $slot }}
        </div>
    @endif
</div>
