@props(['project', 'health' => null])

@php
    /** Compacte projectkaart voor het dashboard: stoplichten, voortgang tegen planning, leider en opleverdatum. */
    $overall = $health?->overall ?? \App\Enums\HealthLight::Grijs;
    $topBorder = match ($overall) {
        \App\Enums\HealthLight::Rood => 'bg-red-500',
        \App\Enums\HealthLight::Oranje => 'bg-amber-500',
        \App\Enums\HealthLight::Groen => 'bg-green-500',
        default => 'bg-surface-600',
    };
    $dotClass = fn (\App\Enums\HealthLight $light) => match ($light) {
        \App\Enums\HealthLight::Rood => 'bg-red-500',
        \App\Enums\HealthLight::Oranje => 'bg-amber-500',
        \App\Enums\HealthLight::Groen => 'bg-green-500',
        default => 'bg-surface-600',
    };

    $progress = (int) ($health?->progress ?? $project->progress);
    $expected = $health?->expectedProgress;
    $behind = $expected !== null && $expected - $progress > 5;

    [$badgeText, $badgeClass] = match (true) {
        $behind => ['loopt achter', 'border-red-500/40 bg-red-500/10 text-red-300'],
        $overall === \App\Enums\HealthLight::Rood => ['actie nodig', 'border-red-500/40 bg-red-500/10 text-red-300'],
        $overall === \App\Enums\HealthLight::Oranje => ['aandacht', 'border-amber-500/40 bg-amber-500/10 text-amber-300'],
        $overall === \App\Enums\HealthLight::Groen => ['op schema', 'border-green-500/40 bg-green-500/10 text-green-300'],
        default => [strtolower($project->status->label()), 'border-surface-600 bg-surface-750 text-slate-300'],
    };

    $leader = $project->projectLeader ?? ($project->relationLoaded('craftsmen') ? $project->craftsmen->first() : null);
    $initials = $leader ? str($leader->name)->explode(' ')->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('') : null;
@endphp

<a href="{{ route('projects.show', $project) }}"
   class="motion-lift group flex flex-col overflow-hidden rounded-2xl border border-surface-700 bg-surface-800 transition hover:border-surface-600">
    <span class="block h-[3px] w-full {{ $topBorder }}"></span>

    <div class="flex flex-1 flex-col p-4">
        <div class="flex items-center justify-between gap-2">
            <span class="truncate text-[11px] font-bold tracking-widest text-slate-400 uppercase">{{ $project->status->label() }}</span>
            @if ($health && $overall !== \App\Enums\HealthLight::Grijs)
                <span class="flex shrink-0 items-center gap-1">
                    @foreach ($health->signals as $signal)
                        <span class="h-2.5 w-2.5 rounded-full {{ $dotClass($signal->light) }}" title="{{ $signal->label }}: {{ $signal->reason }}"></span>
                    @endforeach
                </span>
            @endif
        </div>

        <p class="mt-1.5 text-base leading-snug font-bold text-white">{{ $project->name }}</p>
        <p class="mt-0.5 text-xs text-slate-400">{{ collect([$project->customer->name, $project->city])->filter()->implode(' · ') }}</p>

        <div class="mt-4 flex items-center justify-between text-xs font-semibold">
            <span class="text-slate-300">Voortgang</span>
            <span class="{{ $behind ? 'text-red-400' : 'text-white' }}">{{ $progress }}%</span>
        </div>
        <div class="relative mt-1.5 h-1.5 rounded-full bg-surface-750">
            <div class="h-full rounded-full bg-gradient-to-r from-brand-600 to-brand-400" style="width: {{ $progress }}%"></div>
            @if ($expected !== null)
                <span class="absolute -top-0.5 h-2.5 w-0.5 rounded-full bg-slate-300" style="left: {{ min(100, max(0, $expected)) }}%" title="Volgens planning {{ $expected }}%"></span>
            @endif
        </div>

        <div class="flex-1"></div>
        <div class="mt-4 flex items-center gap-2 border-t border-surface-700 pt-3">
            @if ($leader)
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-steel-500 text-[10px] font-bold text-white" title="{{ $leader->name }}">{{ $initials }}</span>
                <span class="min-w-0 truncate text-xs font-semibold text-slate-300">{{ str($leader->name)->before(' ') }}</span>
            @endif
            <span class="ml-auto flex shrink-0 items-center gap-2">
                @if ($project->end_date_expected)
                    <span class="flex items-center gap-1 text-xs text-slate-400"><x-icon name="calendar" class="h-3.5 w-3.5" />{{ $project->end_date_expected->translatedFormat('j M') }}</span>
                @endif
                <span class="rounded-full border px-2.5 py-0.5 text-[11px] font-semibold whitespace-nowrap {{ $badgeClass }}">{{ $badgeText }}</span>
            </span>
        </div>
    </div>
</a>
