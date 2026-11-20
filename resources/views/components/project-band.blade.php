@props(['project', 'nextStep', 'tab'])

@php
    $customer = $project->customer;
    $photo = $project->cover_photo_path || $project->photos()->where('client_visible', true)->exists()
        ? route('projects.cover', $project)
        : asset('images/dashboard-banner.jpg');

    $current = $project->phases->firstWhere('status', '!=', \App\Enums\PhaseStatus::Gereed);

    // De zes gegevens die altijd in beeld horen bij een dossier.
    $meta = [
        'Projectnummer' => 'PD-'.str_pad((string) $project->id, 4, '0', STR_PAD_LEFT),
        'Opdrachtgever' => $customer?->name ?? 'onbekend',
        'Fase' => $current?->name ?? 'Afgerond',
        'Projectleider' => $project->projectLeader?->name ?? 'nog niet toegewezen',
        'Projectwaarde' => '€ '.number_format((float) $project->value, 2, ',', '.'),
        'Offerte'.($project->quote ? ' '.$project->quote->number : '') => $project->quote?->status->label() ?? 'nog niet',
    ];
@endphp

{{-- De kop van een projectdossier: de merkband uit het prototype. Navy met de
     omslagfoto rechts erachter, zodat de tekst links altijd leesbaar blijft. --}}
<section class="band on-navy mb-[22px]">
    <img src="{{ $photo }}" alt="" aria-hidden="true" class="bimg">

    {{-- Kruimelpad links, acties rechts --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <nav class="flex items-center gap-1.5 text-xs text-muted" aria-label="Kruimelpad">
            <a href="{{ route('projects.index') }}" class="transition hover:text-brand-400">Projectdossiers</a>
            <x-icon name="chevron-right" class="h-3 w-3" />
            <span class="text-ink2">{{ $meta['Projectnummer'] }}</span>
        </nav>

        <div class="acts justify-end">
            @if ($project->chatChannel)
                <a href="{{ route('chat.show', $project->chatChannel) }}" class="btn onnavy"><x-icon name="chat-bubble" /> Chat</a>
            @endif

            @can('manage-crm')
                <a href="{{ route('tasks.index', ['project' => $project->id]) }}" class="btn onnavy"><x-icon name="plus" /> Taak</a>
                <a href="{{ route('projects.edit', $project) }}" class="btn onnavy"><x-icon name="pencil" /> Bewerken</a>
                <a href="{{ route('projects.show', [$project, 'tab' => $nextStep['tab']]) }}" class="btn pri">
                    {{ $nextStep['label'] }} <x-icon name="chevron-right" />
                </a>
            @endcan
        </div>
    </div>

    {{-- Titelblok --}}
    <div class="grid gap-1.5">
        <span class="eyebrow">Projectdossier{{ $project->type ? ', '.$project->type : '' }}</span>
        <h1>{{ $project->name }}</h1>
        <div class="font-display text-base font-medium text-brand-500 lg:text-xl">
            {{ collect([$project->address, $project->city])->filter()->join(', ') ?: 'Adres nog onbekend' }}
        </div>
        <div class="rule mt-1"></div>
    </div>

    {{-- Metadatastrip --}}
    <dl class="bmeta">
        @foreach ($meta as $label => $value)
            <div>
                <span>{{ $label }}</span>
                <b>{{ $value }}</b>
            </div>
        @endforeach
    </dl>
</section>
