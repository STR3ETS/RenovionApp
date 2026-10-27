<x-layouts.app title="Vandaag">

    @if ($onboarding)
        {{-- Aan de slag: korte uitleg voor op het werk, tot de uitvoerder hem uit het menu haalt --}}
        <a href="{{ route('onboarding') }}" class="group mb-4 flex items-center gap-3 rounded-2xl border border-brand-200 bg-brand-50 p-4 transition hover:border-brand-300 hover:shadow-sm">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-500 text-white"><x-icon name="sparkles" class="h-5 w-5" /></span>
            <span class="min-w-0 flex-1">
                <span class="block text-sm font-semibold text-navy-900">Aan de slag: zo werkt het op het werk</span>
                <span class="block text-xs text-brand-700/70">Planning, werkpakketten, checklist afvinken en foto's maken. In vijf stappen uitgelegd.</span>
            </span>
            <x-icon name="chevron-right" class="h-5 w-5 shrink-0 text-brand-400 transition group-hover:translate-x-0.5" />
        </a>
    @endif

    <section class="mb-5 rounded-2xl bg-gradient-to-br from-navy-950 to-navy-900 p-5 text-white lg:p-6">
        <p class="text-[11px] font-bold tracking-widest text-navy-300 uppercase">{{ now()->translatedFormat('l j F') }}</p>
        <h1 class="mt-1 text-xl font-bold lg:text-2xl">
            {{ now()->hour < 12 ? 'Goedemorgen' : (now()->hour < 18 ? 'Goedemiddag' : 'Goedenavond') }} {{ str(auth()->user()->name)->before(' ') }}.
        </h1>
        <p class="mt-1 text-sm text-navy-100">
            @php $vandaag = $planningWeek->filter(fn ($entry) => $entry->date->isToday()); @endphp
            @if ($vandaag->isNotEmpty())
                Vandaag: {{ $vandaag->map(fn ($entry) => $entry->displayTitle())->implode(' en ') }}.
            @else
                Vandaag staat er niets voor je gepland.
            @endif
        </p>
    </section>

    <section class="mb-6">
        <p class="text-[11px] font-bold tracking-widest text-planning-600 uppercase">Planning</p>
        <h2 class="mt-0.5 mb-3 text-base font-bold text-navy-950">Jouw week</h2>
        @if ($planningWeek->isEmpty())
            <x-empty-state title="Geen planning deze week" icon="calendar" category="planning" />
        @else
            <div class="space-y-2">
                @foreach ($planningWeek->groupBy(fn ($entry) => $entry->date->toDateString()) as $entries)
                    @foreach ($entries as $entry)
                        <div class="flex items-center gap-3 rounded-xl border p-3 {{ $entry->type->blockClasses() }}">
                            <span class="w-16 shrink-0 text-xs font-bold">{{ $entry->date->isToday() ? 'Vandaag' : $entry->date->translatedFormat('D j M') }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold">{{ $entry->displayTitle() }}</span>
                                <span class="block text-xs opacity-70">
                                    {{ $entry->type->label() }}
                                    @if ($entry->start_time), {{ substr($entry->start_time, 0, 5) }}@if ($entry->end_time)–{{ substr($entry->end_time, 0, 5) }}@endif @endif
                                </span>
                            </span>
                            @if ($entry->project)
                                <a href="{{ route('projects.show', $entry->project) }}" class="text-xs font-semibold whitespace-nowrap opacity-70 hover:opacity-100">Project →</a>
                            @endif
                        </div>
                    @endforeach
                @endforeach
            </div>
        @endif
    </section>

    <section>
        <p class="text-[11px] font-bold tracking-widest text-planning-600 uppercase">Taken</p>
        <h2 class="mt-0.5 mb-3 text-base font-bold text-navy-950">Jouw taken</h2>
        @if ($taken->isEmpty())
            <x-empty-state title="Geen open taken" icon="clipboard-check" category="planning" />
        @else
            <div class="space-y-2">
                @foreach ($taken as $task)
                    <div class="flex items-center gap-3 rounded-xl border border-gray-200 border-l-4 bg-white p-3 {{ $task->isOverdue() ? 'border-l-red-500' : 'border-l-planning-500' }}">
                        <button
                            x-data
                            @click="patchJson('{{ route('tasks.status', $task) }}', { status: 'afgerond' }).then(() => window.location.reload())"
                            class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2 border-gray-300 transition hover:border-green-500 hover:bg-green-50"
                            title="Afronden"></button>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-navy-900">{{ $task->title }}</span>
                            <span class="block text-xs text-gray-500">
                                {{ $task->project?->name ?? $task->customer?->name ?? 'Algemeen' }}
                                @if ($task->deadline), {{ $task->deadline->isToday() ? 'vandaag' : $task->deadline->translatedFormat('j M') }} @endif
                            </span>
                        </span>
                        <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $task->priority->dotClasses() }}"></span>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

</x-layouts.app>
