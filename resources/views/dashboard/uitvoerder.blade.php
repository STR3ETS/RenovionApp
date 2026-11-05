<x-layouts.app title="Vandaag">

    {{-- Uitvoerder-start (mockup §18, telefoon): project van vandaag voorop, dan wat er vandaag af moet. --}}
    @nativeapp
        <section data-enter class="app-band mb-4">
            <img src="{{ asset('images/os-hero.jpg') }}" alt="" class="bimg">
            <div>
                <p class="eyebrow">{{ now()->translatedFormat('l j F Y') }}</p>
                <h1 class="mt-1.5">
                    {{ now()->hour < 12 ? 'Goedemorgen' : (now()->hour < 18 ? 'Goedemiddag' : 'Goedenavond') }}, <span>{{ str(auth()->user()->name)->before(' ') }}</span>
                </h1>
                <span class="rule"></span>
            </div>
        </section>
    @else
    <p class="text-[11px] font-bold tracking-widest text-gray-600 uppercase">{{ now()->translatedFormat('l j F') }}</p>
    <h1 class="mt-0.5 mb-4 text-xl font-bold text-navy-950">
        {{ now()->hour < 12 ? 'Goedemorgen' : (now()->hour < 18 ? 'Goedemiddag' : 'Goedenavond') }} {{ str(auth()->user()->name)->before(' ') }}.
    </h1>
    @endnativeapp

    @if ($project)
        @php
            $phase = $project->currentPhase();
            [$signaal, $signaalTekst] = match (true) {
                $project->isOverdue() => ['red', 'Achter op schema'],
                $phase === null, $phase->status === \App\Enums\PhaseStatus::Bezig => ['green', 'Op schema'],
                default => [$phase->status->dotColor(), $phase->status->label()],
            };
            $tegels = [
                [
                    'label' => 'Chat',
                    'icon' => 'chat-bubble',
                    'href' => $project->chatChannel ? route('chat.show', $project->chatChannel) : route('chat.index'),
                    'sub' => $chatOngelezen > 0 ? $chatOngelezen.' nieuw' : 'Geen nieuwe',
                    'accent' => $chatOngelezen > 0,
                ],
                [
                    'label' => 'Werk',
                    'icon' => 'clipboard-check',
                    'href' => route('projects.show', [$project, 'tab' => 'uitvoering']),
                    'sub' => $project->open_werk_count > 0 ? $project->open_werk_count.' open' : 'Alles af',
                    'accent' => $project->open_werk_count > 0,
                ],
                [
                    'label' => "Foto's",
                    'icon' => 'photo',
                    'href' => route('projects.show', [$project, 'tab' => 'fotos']),
                    'sub' => $project->nieuwe_fotos_count > 0 ? $project->nieuwe_fotos_count.' nieuw' : $project->photos_count.' totaal',
                    'accent' => false,
                ],
                [
                    'label' => 'Documenten',
                    'icon' => 'document-text',
                    'href' => route('projects.show', $project).'#documenten',
                    'sub' => $project->documents_count === 1 ? '1 bestand' : $project->documents_count.' bestanden',
                    'accent' => false,
                ],
            ];
        @endphp

        <section class="mb-6 rounded-2xl border border-gray-200 bg-white p-4">
            <a href="{{ route('projects.show', $project) }}" class="flex items-start justify-between gap-3">
                <span class="min-w-0">
                    <span class="block truncate text-xl font-bold text-navy-950">{{ $project->name }}</span>
                    <span class="block truncate text-sm text-gray-500">{{ $project->customer->name }}{{ $project->city ? ', '.$project->city : '' }}</span>
                </span>
                <x-icon name="chevron-right" class="mt-1.5 h-5 w-5 text-gray-300" />
            </a>

            <a href="{{ route('projects.show', $project) }}" class="mt-3 block h-40 overflow-hidden rounded-xl bg-gradient-to-br from-navy-700 via-navy-900 to-navy-950">
                @if ($project->coverPhotoPath())
                    <img src="{{ route('projects.cover', $project) }}" alt="{{ $project->name }}" class="h-full w-full object-cover">
                @else
                    <span class="flex h-full items-center justify-center">
                        <x-icon name="building-office" class="h-10 w-10 text-navy-500" />
                    </span>
                @endif
            </a>

            <div class="mt-4 flex items-center gap-3">
                <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-gray-100">
                    <div class="h-full rounded-full bg-brand-500" style="width: {{ $project->progress }}%"></div>
                </div>
                <span class="text-sm font-bold text-navy-950">{{ $project->progress }}%</span>
            </div>
            <p class="mt-1.5 flex items-center gap-1.5 text-sm">
                <span class="font-semibold text-navy-900">{{ $phase?->name ?? $project->status->label() }}</span>
                <x-signal-dot :color="$signaal" />
                <span class="font-medium {{ $signaal === 'green' ? 'text-green-700' : ($signaal === 'red' ? 'text-red-600' : 'text-amber-700') }}">{{ $signaalTekst }}</span>
            </p>

            <div class="mt-4 grid grid-cols-4 gap-2 border-t border-gray-100 pt-4">
                @foreach ($tegels as $tegel)
                    <a href="{{ $tegel['href'] }}" class="flex min-w-0 flex-col items-center gap-1 rounded-xl py-1 text-center transition active:bg-gray-50">
                        <span class="flex h-12 w-12 items-center justify-center rounded-2xl border border-gray-200 bg-white text-navy-900 shadow-sm">
                            <x-icon :name="$tegel['icon']" class="h-6 w-6" />
                        </span>
                        <span class="w-full truncate text-xs font-semibold text-navy-900">{{ $tegel['label'] }}</span>
                        <span class="w-full truncate text-[10px] font-medium {{ $tegel['accent'] ? 'text-brand-700' : 'text-gray-500' }}">{{ $tegel['sub'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @else
        <div class="mb-6">
            <x-empty-state title="Nog geen project" subtitle="Zodra je op een project wordt ingedeeld, staat het hier." icon="building-office" category="project" />
        </div>
    @endif

    {{-- Vandaag: afvinken zoals in de mockup --}}
    <section class="mb-6">
        <h2 class="mb-2 text-base font-bold text-navy-950">Vandaag</h2>
        @if ($takenVandaag->isEmpty())
            <p class="rounded-2xl border border-dashed border-gray-200 bg-white p-4 text-sm text-gray-500">Niets meer voor vandaag. Lekker bezig.</p>
        @else
            <div class="divide-y divide-gray-100 rounded-2xl border border-gray-200 bg-white">
                @foreach ($takenVandaag as $task)
                    @include('dashboard.partials.uitvoerder-taak', ['task' => $task])
                @endforeach
            </div>
        @endif
    </section>

    <section class="mb-6">
        <h2 class="mb-2 text-base font-bold text-navy-950">Jouw week</h2>
        @if ($planningWeek->isEmpty())
            <x-empty-state title="Geen planning deze week" icon="calendar" category="planning" />
        @else
            <div class="divide-y divide-gray-100 rounded-2xl border border-gray-200 bg-white">
                @foreach ($planningWeek as $entry)
                    <a href="{{ $entry->project ? route('projects.show', $entry->project) : route('planning.index') }}" class="flex items-center gap-3 p-3">
                        <span class="w-14 shrink-0 text-xs font-bold {{ $entry->date->isToday() ? 'text-brand-700' : 'text-gray-500' }}">{{ $entry->date->isToday() ? 'Vandaag' : $entry->date->translatedFormat('D j M') }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-navy-900">{{ $entry->displayTitle() }}</span>
                            <span class="block truncate text-xs text-gray-500">
                                {{ $entry->type->label() }}@if ($entry->start_time), {{ substr($entry->start_time, 0, 5) }}@if ($entry->end_time)-{{ substr($entry->end_time, 0, 5) }}@endif @endif
                            </span>
                        </span>
                        <x-icon name="chevron-right" class="h-4 w-4 text-gray-300" />
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    @if ($takenLater->isNotEmpty())
        <section>
            <h2 class="mb-2 text-base font-bold text-navy-950">Later</h2>
            <div class="divide-y divide-gray-100 rounded-2xl border border-gray-200 bg-white">
                @foreach ($takenLater as $task)
                    @include('dashboard.partials.uitvoerder-taak', ['task' => $task])
                @endforeach
            </div>
        </section>
    @endif

</x-layouts.app>
