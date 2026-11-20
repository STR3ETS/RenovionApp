@props(['project', 'health' => null])

@php
    /** Projectkaart in de dossierlijst (pcard in het prototype): nummer en status, stoplichten, naam, soort en waarde, voortgang. */
    $tone = fn (\App\Enums\HealthLight $light) => match ($light) {
        \App\Enums\HealthLight::Rood => 'r',
        \App\Enums\HealthLight::Oranje => 'o',
        \App\Enums\HealthLight::Groen => 'g',
        default => 'n',
    };

    $hasLights = $health && $health->overall !== \App\Enums\HealthLight::Grijs;
    $leader = $project->projectLeader ?? ($project->relationLoaded('craftsmen') ? $project->craftsmen->first() : null);
    $initials = $leader ? str($leader->name)->explode(' ')->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('') : null;
@endphp

<a href="{{ route('projects.show', $project) }}" class="pcard">
    <div class="flex items-center justify-between gap-2">
        <span class="id">{{ $project->number() }}</span>
        <x-status-badge :status="$project->status" />
    </div>

    @if ($hasLights)
        <div class="pc-h flex items-center justify-between gap-2" title="{{ $health->biggestBlocker ?? 'Alle stoplichten groen' }}">
            <span class="text-xs text-muted">Planning, budget, kwaliteit, klant</span>
            <span class="hls">
                @foreach ($health->signals as $signal)
                    <span class="hl hl-{{ $tone($signal->light) }}" title="{{ $signal->label }}: {{ $signal->reason }}"></span>
                @endforeach
            </span>
        </div>
    @endif

    <div class="min-w-0">
        <h4 class="truncate">{{ $project->name }}</h4>
        <span class="block truncate text-muted">{{ $project->customer->name }}{{ $project->city ? ', '.$project->city : '' }}</span>
    </div>

    <div class="flex items-center justify-between gap-2">
        <span class="truncate text-muted">{{ $project->type ?: 'Project' }}</span>
        @can('manage-crm')
            <b class="shrink-0 tabular-nums">€ {{ number_format((float) $project->value, 0, ',', '.') }}</b>
        @endcan
    </div>

    @if ($project->status === \App\Enums\ProjectStatus::Voorbereiding && $project->progress === 0 && $leader)
        <div class="flex items-center gap-2 text-xs">
            <span class="av sm" title="{{ $leader->name }}">{{ $initials }}</span>
            <span class="truncate text-muted">{{ $leader->name }}</span>
        </div>
    @else
        <div>
            <div class="flex items-center justify-between text-xs">
                <span class="text-muted">Voortgang</span>
                <span class="tabular-nums {{ $project->isOverdue() ? 'dn' : '' }}">{{ $project->progress }}%</span>
            </div>
            <div class="bar mt-1"><i style="width: {{ $project->progress }}%"></i></div>
        </div>
    @endif
</a>
