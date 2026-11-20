@props([
    'dossier',
    // Waar het kruimelpad naar teruggaat.
    'crumb' => 'Projectdossiers',
    'crumbUrl' => null,
])

@php
    $photo = $dossier->project && ($dossier->project->cover_photo_path || $dossier->project->photos()->where('client_visible', true)->exists())
        ? route('projects.cover', $dossier->project)
        : asset('images/dashboard-banner.jpg');
@endphp

{{-- De kop van een dossier (merkband uit het prototype): navy met de foto
     rechts erachter. Kruimelpad links, acties rechts, daaronder de titel en
     de zes vaste gegevens. Dezelfde kop voor aanvraag, offerte en project. --}}
<section class="band on-navy mb-[22px]">
    <img src="{{ $photo }}" alt="" aria-hidden="true" class="bimg">

    <div class="flex flex-wrap items-start justify-between gap-4">
        <nav class="flex items-center gap-1.5 text-xs text-muted" aria-label="Kruimelpad">
            <a href="{{ $crumbUrl ?? route('projects.index') }}" class="transition hover:text-brand-400">{{ $crumb }}</a>
            <x-icon name="chevron-right" class="h-3 w-3" />
            <span class="text-ink2">{{ $dossier->number() }}</span>
        </nav>

        @if (trim($slot))
            <div class="acts justify-end">{{ $slot }}</div>
        @endif
    </div>

    <div class="grid gap-1.5">
        <span class="eyebrow">Projectdossier{{ $dossier->kind() ? ', '.$dossier->kind() : '' }}</span>
        <h1>{{ $dossier->title() }}</h1>
        <div class="font-display text-base font-medium text-brand-500 lg:text-xl">{{ $dossier->address() ?? 'Adres nog onbekend' }}</div>
        <div class="rule mt-1"></div>
    </div>

    <dl class="bmeta">
        @foreach ($dossier->meta() as $label => $value)
            <div>
                <span>{{ $label }}</span>
                <b>{{ $value }}</b>
            </div>
        @endforeach
    </dl>
</section>
