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
        'Projectwaarde' => 'EUR '.number_format((float) $project->value, 2, ',', '.'),
        'Offerte'.($project->quote ? ' '.$project->quote->number : '') => $project->quote?->status->label() ?? 'nog niet',
    ];
@endphp

{{-- De kop van een projectdossier: foto met een verloop eroverheen, zodat de
     tekst links altijd leesbaar blijft ongeacht de foto. --}}
<section class="relative mb-4 overflow-hidden rounded-2xl">
    <img src="{{ $photo }}" alt="" aria-hidden="true" class="absolute inset-0 h-full w-full object-cover object-right">
    <div class="absolute inset-0 bg-gradient-to-r from-navy-950/98 via-navy-950/88 to-navy-950/25"></div>

    <div class="relative grid gap-5 px-6 py-6 lg:px-8">
        {{-- Kruimelpad links, acties rechts --}}
        <div class="flex flex-wrap items-start justify-between gap-4">
            <nav class="flex items-center gap-1.5 text-xs text-slate-400">
                <a href="{{ route('projects.index') }}" class="transition hover:text-white">Projectdossiers</a>
                <x-icon name="chevron-right" class="h-3 w-3" />
                <span class="text-slate-300">{{ $meta['Projectnummer'] }}</span>
            </nav>

            <div class="flex flex-wrap items-center gap-2">
                @if ($project->chatChannel)
                    <a href="{{ route('chat.show', $project->chatChannel) }}"
                       class="flex items-center gap-1.5 rounded-full border border-white/20 px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-white/10">
                        <x-icon name="chat-bubble" class="h-4 w-4" /> Chat
                    </a>
                @endif

                @can('manage-crm')
                    <a href="{{ route('tasks.index', ['project' => $project->id]) }}"
                       class="flex items-center gap-1.5 rounded-full border border-white/20 px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-white/10">
                        <x-icon name="plus" class="h-4 w-4" /> Taak
                    </a>
                    <a href="{{ route('projects.edit', $project) }}"
                       class="flex items-center gap-1.5 rounded-full border border-white/20 px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-white/10">
                        <x-icon name="pencil" class="h-4 w-4" /> Bewerken
                    </a>
                    <a href="{{ route('projects.show', [$project, 'tab' => $nextStep['tab']]) }}"
                       class="flex items-center gap-1.5 rounded-full bg-brand-500 px-4 py-2 text-sm font-bold text-white transition hover:bg-brand-600">
                        {{ $nextStep['label'] }} <x-icon name="chevron-right" class="h-4 w-4" />
                    </a>
                @endcan
            </div>
        </div>

        {{-- Titelblok --}}
        <div class="grid gap-1.5">
            <p class="text-[11px] font-bold tracking-[0.14em] text-slate-400 uppercase">
                Projectdossier @if ($project->type), {{ $project->type }} @endif
            </p>
            <h1 class="text-2xl leading-tight font-bold text-white lg:text-4xl">{{ $project->name }}</h1>
            <p class="text-base font-semibold text-brand-500 lg:text-lg">
                {{ collect([$project->address, $project->city])->filter()->join(', ') ?: 'Adres nog onbekend' }}
            </p>
            <span class="mt-1 block h-[3px] w-14 rounded-full bg-brand-500"></span>
        </div>

        {{-- Metadatastrip --}}
        <dl class="grid max-w-5xl gap-3 border-t border-white/10 pt-4 sm:grid-cols-3 lg:grid-cols-6">
            @foreach ($meta as $label => $value)
                <div class="min-w-0">
                    <dt class="text-[10px] font-semibold tracking-[0.1em] text-slate-400 uppercase">{{ $label }}</dt>
                    <dd class="truncate text-sm font-semibold text-white">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</section>
