@props(['project', 'health' => null])

@php
    /** Compacte projectkaart voor Vandaag (uv-card in het prototype): nummer, stoplichten, voortgang tegen planning, leider en opleverdatum. */
    $overall = $health?->overall ?? \App\Enums\HealthLight::Grijs;
    $tone = fn (\App\Enums\HealthLight $light) => match ($light) {
        \App\Enums\HealthLight::Rood => 'r',
        \App\Enums\HealthLight::Oranje => 'o',
        \App\Enums\HealthLight::Groen => 'g',
        default => 'n',
    };

    $progress = (int) ($health?->progress ?? $project->progress);
    $expected = $health?->expectedProgress;
    $behind = $expected !== null && $expected - $progress > 5;

    [$badgeText, $badgeClass] = match (true) {
        $behind => ['loopt achter', 'bad'],
        $overall === \App\Enums\HealthLight::Rood => ['actie nodig', 'bad'],
        $overall === \App\Enums\HealthLight::Oranje => ['aandacht', 'warn'],
        $overall === \App\Enums\HealthLight::Groen => ['op schema', 'ok'],
        default => [strtolower($project->status->label()), 'mut'],
    };

    $leader = $project->projectLeader ?? ($project->relationLoaded('craftsmen') ? $project->craftsmen->first() : null);
    $initials = $leader ? str($leader->name)->explode(' ')->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('') : null;
@endphp

<a href="{{ route('projects.show', $project) }}" class="uv-card uv-{{ $tone($overall) }}">
    <span class="uv-top">
        <span class="uv-id">{{ $project->number() }}</span>
        @if ($health && $overall !== \App\Enums\HealthLight::Grijs)
            <span class="hls">
                @foreach ($health->signals as $signal)
                    <span class="hl hl-{{ $tone($signal->light) }}" title="{{ $signal->label }}: {{ $signal->reason }}"></span>
                @endforeach
            </span>
        @else
            <span class="pill mut">{{ $project->status->label() }}</span>
        @endif
    </span>

    <b class="uv-n">{{ $project->name }}</b>
    <small class="uv-s">{{ collect([$project->customer->name, $project->city])->filter()->implode(', ') }}</small>

    <span class="uv-pg">
        <span class="flex items-center justify-between"><span>Voortgang</span><b class="{{ $behind ? 'dn' : '' }}">{{ $progress }}%</b></span>
        <span class="bar block">
            <i style="width: {{ $progress }}%"></i>
            @if ($expected !== null)
                <u style="left: {{ min(100, max(0, $expected)) }}%" title="Volgens planning {{ $expected }}%"></u>
            @endif
        </span>
    </span>

    <span class="uv-f">
        @if ($leader)
            <span class="av sm" title="{{ $leader->name }}">{{ $initials }}</span>
            <span class="min-w-0 truncate">{{ str($leader->name)->before(' ') }}</span>
        @endif
        @if ($project->end_date_expected)
            <span class="uv-d"><x-icon name="calendar" />{{ $project->end_date_expected->translatedFormat('j M') }}</span>
        @endif
        <span class="pill {{ $badgeClass }} {{ $project->end_date_expected ? '' : 'ml-auto' }}">{{ $badgeText }}</span>
    </span>
</a>
