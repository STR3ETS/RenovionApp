@props(['project'])

@php
    /** Fotokaart per project (mockup §18): handmatige omslag of het laatste klantzichtbare foto-bewijs. */
    $phase = $project->relationLoaded('phases') ? $project->currentPhase() : null;
    $hasCover = $project->cover_photo_path !== null || ($project->photos_count ?? 0) > 0;
@endphp

@nativeapp
    {{-- Projectkaart van de iOS/Android-app (prototype Renovion OS): tekst vooraan, geen foto. --}}
    <a href="{{ route('projects.show', $project) }}" data-zoom-card class="app-pcard">
        <span class="flex items-center justify-between gap-2">
            <span class="id truncate">{{ $phase?->name ?? $project->status->label() }}</span>
            <x-status-badge :status="$project->status" />
        </span>
        <h4>{{ $project->name }}</h4>
        <span class="sub truncate">{{ $project->customer->name }}{{ $project->city ? ', '.$project->city : '' }}</span>
        @can('manage-crm')
            <span class="flex items-center justify-between gap-2 text-sm">
                <span class="text-gray-500">Projectwaarde</span>
                <b class="font-[family-name:var(--font-display)] font-semibold text-navy-900">€ {{ number_format((float) $project->value, 0, ',', '.') }}</b>
            </span>
        @endcan
        <span class="block">
            <span class="mb-1 flex items-center justify-between text-xs text-gray-500">
                <span>Voortgang</span>
                <span class="font-semibold">{{ $project->progress }}%</span>
            </span>
            <span class="app-bar"><i style="width: {{ $project->progress }}%"></i></span>
        </span>
        @if ($project->relationLoaded('projectLeader') && $project->projectLeader)
            <span class="flex items-center gap-2 text-[13px] text-gray-600">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-brand-500 text-[10px] font-bold text-white">{{ str($project->projectLeader->name)->explode(' ')->take(2)->map(fn ($deel) => str($deel)->substr(0, 1)->upper())->implode('') }}</span>
                {{ $project->projectLeader->name }}
            </span>
        @endif
    </a>
@else
<a href="{{ route('projects.show', $project) }}" data-zoom-card
   class="group flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white transition hover:border-brand-400 hover:shadow-md">
    <div class="relative h-28 overflow-hidden bg-gradient-to-br from-navy-700 via-navy-900 to-navy-950">
        @if ($hasCover)
            <img src="{{ route('projects.cover', $project) }}" alt="{{ $project->name }}" loading="lazy"
                 class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
        @else
            <span class="flex h-full items-center justify-center">
                <x-icon name="building-office" class="h-9 w-9 text-navy-500 transition group-hover:text-navy-400" />
            </span>
        @endif
        <span class="absolute top-2.5 right-2.5"><x-status-badge :status="$project->status" /></span>
    </div>

    <div class="flex flex-1 flex-col p-4">
        <p class="truncate text-sm font-bold text-navy-900">{{ $project->name }}</p>
        <p class="mt-0.5 truncate text-xs text-gray-500">{{ $project->customer->name }}{{ $project->city ? ', '.$project->city : '' }}</p>

        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-gray-100">
            <div class="h-full rounded-full bg-brand-500" style="width: {{ $project->progress }}%"></div>
        </div>
        <div class="mt-1.5 flex items-center justify-between gap-2">
            <span class="flex min-w-0 items-center gap-1.5 text-xs font-medium text-gray-500">
                <x-signal-dot :color="$project->isOverdue() ? 'red' : ($phase?->status->dotColor() ?? 'gray')" />
                <span class="truncate">{{ $phase?->name ?? $project->status->label() }}</span>
            </span>
            <span class="shrink-0 text-xs font-semibold text-gray-500">{{ $project->progress }}%</span>
        </div>

        <div class="mt-3 flex items-center justify-between gap-2">
            @if ($project->relationLoaded('craftsmen') && $project->craftsmen->isNotEmpty())
                <span class="flex -space-x-1.5">
                    @foreach ($project->craftsmen->take(3) as $member)
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-brand-100 text-[10px] font-bold text-brand-700 ring-2 ring-white" title="{{ $member->name }}">
                            {{ str($member->name)->substr(0, 1)->upper() }}
                        </span>
                    @endforeach
                    @if ($project->craftsmen->count() > 3)
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-gray-100 text-[10px] font-bold text-gray-500 ring-2 ring-white">+{{ $project->craftsmen->count() - 3 }}</span>
                    @endif
                </span>
            @else
                <span></span>
            @endif
            @can('manage-crm')
                <span class="shrink-0 text-xs font-semibold text-navy-900">€ {{ number_format((float) $project->value, 0, ',', '.') }}</span>
            @endcan
        </div>
    </div>
</a>
@endnativeapp
