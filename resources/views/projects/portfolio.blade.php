<x-layouts.app title="Portfolio">

    <x-page-header category="project" title="Portfolio" subtitle="{{ $rows->count() }} lopende {{ $rows->count() === 1 ? 'project' : 'projecten' }} met stoplichten">
        <a href="{{ route('projects.index') }}" class="btn">Projectenlijst</a>
    </x-page-header>

    {{-- Tellers per licht, meteen als filter --}}
    <div class="mb-4 grid grid-cols-2 gap-2 lg:grid-cols-4">
        @foreach ([\App\Enums\HealthLight::Rood, \App\Enums\HealthLight::Oranje, \App\Enums\HealthLight::Groen, \App\Enums\HealthLight::Grijs] as $light)
            <a href="{{ route('portfolio', array_filter(['licht' => $filter === $light ? null : $light->value, 'sorteer' => $sort])) }}"
               class="flex items-center gap-3 rounded-2xl border p-3.5 transition hover:shadow-sm {{ $filter === $light ? 'ring-2 ring-navy-950 ring-offset-1' : '' }} {{ $light->cardClasses() }}">
                <x-signal-dot :color="$light->dotColor()" class="h-3 w-3" />
                <span class="min-w-0">
                    <span class="block text-xl leading-tight font-bold text-ink">{{ $counts[$light->value] ?? 0 }}</span>
                    <span class="block truncate text-xs font-medium {{ $light->textClasses() }}">{{ $light->label() }}</span>
                </span>
            </a>
        @endforeach
    </div>

    {{-- Sortering --}}
    <div class="mb-4 flex flex-wrap items-center gap-1.5">
        <span class="mr-1 text-xs font-semibold text-faint">Sorteer op</span>
        @foreach (['gezondheid' => 'Gezondheid', 'oplevering' => 'Opleverdatum', 'voortgang' => 'Voortgang', 'waarde' => 'Waarde'] as $key => $label)
            <a href="{{ route('portfolio', array_filter(['licht' => $filter?->value, 'sorteer' => $key])) }}"
               class="tab {{ $sort === $key ? 'on' : '' }}">{{ $label }}</a>
        @endforeach
    </div>

    {{-- Projecten --}}
    <div class="space-y-2">
        @forelse ($rows as $row)
            @php $project = $row['project']; $health = $row['health']; @endphp
            <a href="{{ route('projects.show', $project) }}"
               class="block rounded-2xl border border-surface-700 border-l-4 bg-surface-800 p-4 transition hover:shadow-sm {{ $health->overall->borderClasses() }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <x-signal-dot :color="$health->overall->dotColor()" class="h-2.5 w-2.5" />
                            <p class="truncate text-sm font-bold text-ink">{{ $project->name }}</p>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $health->overall->badgeClasses() }}">{{ $health->overall->label() }}</span>
                        </div>
                        <p class="mt-0.5 truncate text-xs text-muted">
                            {{ $project->customer->name }}{{ $project->city ? ', '.$project->city : '' }}
                            @if ($project->projectLeader), {{ $project->projectLeader->name }} @endif
                        </p>
                    </div>
                    <x-health-lights :health="$health" compact />
                </div>

                <div class="mt-3 grid gap-3 lg:grid-cols-3">
                    <x-progress-vs-plan :health="$health" class="lg:col-span-1" />

                    <div class="text-xs">
                        <p class="font-semibold text-muted">Volgende mijlpaal</p>
                        <p class="mt-1 font-bold text-ink">{{ $health->nextMilestone ?? 'Alles gereed' }}</p>
                        <p class="text-faint">{{ $health->nextMilestoneDue ?? 'geen datum gepland' }}</p>
                    </div>

                    <div class="text-xs">
                        <p class="font-semibold text-muted">Grootste blokkade</p>
                        @if ($health->biggestBlocker)
                            <p class="mt-1 leading-relaxed font-medium {{ $health->overall->textClasses() }}">{{ $health->biggestBlocker }}</p>
                        @else
                            <p class="mt-1 text-faint">Geen, alles loopt.</p>
                        @endif
                    </div>
                </div>
            </a>
        @empty
            <x-empty-state title="Geen projecten in deze weergave"
                           subtitle="Pas het filter aan of start een project; stoplichten verschijnen zodra een project in uitvoering is."
                           icon="building-office" category="project" />
        @endforelse
    </div>

</x-layouts.app>
