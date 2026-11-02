@php
    use App\Enums\JobReportType;
    use App\Enums\JobStatus;

    $action = $job->status->primaryAction();
    $einde = $entry->end_date ?? $entry->date;
    $openStap = $job->steps->firstWhere(fn ($step) => ! $step->isDone());
    $missend = $job->stepsMissingEvidence();
    $fotos = $job->steps->flatMap->photos;
    $tabs = [
        'stappen' => ['Stappen', $job->steps->whereNull('completed_at')->count()],
        'info' => ['Info', 0],
        'fotos' => ["Foto's", $fotos->count()],
        'meldingen' => ['Meldingen', $job->openReports()->count()],
    ];
@endphp

<x-layouts.app :title="$entry->displayTitle()">
    <div class="mb-4 flex items-center justify-between gap-3">
        <a href="{{ route('jobs.index') }}" class="flex items-center gap-1 text-sm font-semibold text-slate-400 transition hover:text-white">
            <x-icon name="chevron-left" class="h-4 w-4" /> Mijn klussen
        </a>
        <span class="rounded-full px-3 py-1 text-xs font-bold {{ $job->status->badgeClasses() }}">{{ $job->status->label() }}</span>
    </div>

    <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <p class="text-[11px] font-bold tracking-widest text-project-400 uppercase">{{ $project?->name }}</p>
            <h1 class="text-xl font-bold text-white lg:text-2xl">{{ $entry->displayTitle() }}</h1>
            @if ($project?->address)
                <a href="https://maps.google.com/?q={{ urlencode(collect([$project->address, $project->city])->filter()->join(' ')) }}"
                   target="_blank" rel="noopener"
                   class="mt-1 flex items-center gap-1 text-sm text-brand-400 transition hover:text-brand-300">
                    <x-icon name="map-pin" class="h-4 w-4" />
                    {{ collect([$project->address, $project->city])->filter()->join(', ') }}
                </a>
            @endif
            <p class="mt-1 text-xs text-slate-500">
                {{ $entry->date->translatedFormat('j M') }}@if (! $einde->isSameDay($entry->date)) t/m {{ $einde->translatedFormat('j M') }}@endif
                @if ($job->dayLabel()), {{ $job->dayLabel() }}@endif, {{ $job->user->name }}
            </p>
        </div>

        <div class="flex shrink-0 items-center gap-3">
            <div class="text-right">
                <p class="text-2xl font-bold text-white">{{ $job->progress() }}%</p>
                <p class="text-xs text-slate-500">{{ $job->completedSteps() }} van {{ $job->steps->count() }} stappen</p>
            </div>
        </div>
    </div>

    {{-- De grote knoppen: één handeling per keer, duim-vriendelijk --}}
    @if ($canExecute)
        <div class="mb-5 space-y-2">
            @if ($action && $job->status === JobStatus::Onderweg)
                {{-- Starten gaat via de veiligheidscheck --}}
                <div x-data="{ open: false, antwoorden: {} }">
                    <button @click="open = true"
                            class="flex w-full items-center justify-center gap-2 rounded-2xl bg-brand-500 py-4 text-base font-bold text-white transition hover:bg-brand-600">
                        <x-icon name="play" class="h-5 w-5" /> Start werk
                    </button>

                    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-navy-950/70 backdrop-blur-sm sm:items-center">
                        <div @click.outside="open = false" class="max-h-[90vh] w-full overflow-y-auto rounded-t-2xl border border-surface-700 bg-surface-800 p-5 sm:max-w-md sm:rounded-2xl">
                            <h2 class="text-base font-bold text-white">Veiligheidscheck</h2>
                            <p class="mt-1 mb-4 text-sm text-slate-400">
                                Laatste Minuut Risico Analyse. Alles moet ja zijn voordat je begint.
                            </p>

                            <form method="POST" action="{{ route('jobs.status', $job) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="bezig">

                                <div class="space-y-2">
                                    @foreach ($lmra as $i => $vraag)
                                        <label class="flex items-start gap-3 rounded-xl border border-surface-700 bg-surface-850 p-3">
                                            <input type="checkbox" name="lmra[{{ $i }}]" value="1" x-model="antwoorden[{{ $i }}]"
                                                   class="mt-0.5 h-5 w-5 shrink-0 rounded border-surface-600 bg-surface-750 text-brand-500 focus:ring-brand-500">
                                            <span class="text-sm text-slate-200">{{ $vraag }}</span>
                                        </label>
                                    @endforeach
                                </div>

                                <div class="mt-4 flex gap-2">
                                    <button type="button" @click="open = false"
                                            class="flex-1 rounded-xl border border-surface-600 py-3 text-sm font-semibold text-slate-300">Annuleren</button>
                                    <button type="submit"
                                            :disabled="Object.values(antwoorden).filter(Boolean).length < {{ count($lmra) }}"
                                            class="flex-1 rounded-xl bg-brand-500 py-3 text-sm font-bold text-white transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-40">
                                        Start werk
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @elseif ($action)
                <form method="POST" action="{{ route('jobs.status', $job) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="{{ $action['next']->value }}">
                    <button type="submit"
                            class="flex w-full items-center justify-center gap-2 rounded-2xl py-4 text-base font-bold transition {{ $job->status === JobStatus::Bezig ? 'border border-surface-600 text-slate-200 hover:border-surface-600 hover:text-white' : 'bg-brand-500 text-white hover:bg-brand-600' }}">
                        <x-icon :name="$action['icon']" class="h-5 w-5" /> {{ $action['label'] }}
                    </button>
                </form>
            @endif

            @if ($job->status->isActive())
                @include('jobs.partials.finish')
            @endif

            @if ($job->status === JobStatus::Klaar)
                <div class="flex items-center gap-3 rounded-2xl border border-green-500/25 bg-green-500/10 p-4">
                    <x-icon name="check" class="h-6 w-6 shrink-0 text-green-300" />
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-white">Klus afgerond en werkbon ingediend</p>
                        @if ($workOrder)
                            <p class="truncate text-xs text-slate-400">
                                <a href="{{ route('work-orders.show', $workOrder) }}" class="font-semibold text-green-300 hover:underline">{{ $workOrder->number }}</a>,
                                {{ $workOrder->status === \App\Enums\WorkOrderStatus::Controle
                                    ? 'wacht op controle door '.($project?->projectLeader?->name ?? 'de projectleider')
                                    : $workOrder->status->label() }}
                            </p>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- Tabbladen binnen de klus --}}
    <nav class="scrollbar-none mb-4 flex gap-1 overflow-x-auto rounded-xl bg-surface-850 p-1">
        @foreach ($tabs as $key => [$label, $count])
            <a href="{{ route('jobs.show', [$entry, 'tab' => $key]) }}"
               class="flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold transition {{ $tab === $key ? 'bg-surface-700 text-white' : 'text-slate-400 hover:text-slate-200' }}">
                {{ $label }}
                @if ($count > 0)
                    <span class="rounded-full bg-brand-500 px-1.5 text-[10px] font-bold text-white">{{ $count }}</span>
                @endif
            </a>
        @endforeach
    </nav>

    @if ($tab === 'stappen')
        @if (in_array($job->status, [JobStatus::Gepland, JobStatus::Onderweg], true))
            <div class="mb-4 flex items-start gap-3 rounded-xl border border-lead-500/25 bg-lead-500/10 p-4">
                <x-icon name="flag" class="h-5 w-5 shrink-0 text-lead-300" />
                <p class="text-sm text-slate-200">
                    Druk op <b class="text-white">Start werk</b>: je doorloopt eerst de veiligheidscheck.
                </p>
            </div>
        @endif

        <div class="space-y-2">
            @foreach ($job->steps as $step)
                @include('jobs.partials.step', ['step' => $step, 'isOpen' => $openStap?->id === $step->id])
            @endforeach
        </div>
    @endif

    @if ($tab === 'info')
        @include('jobs.partials.info')
    @endif

    @if ($tab === 'fotos')
        @if ($fotos->isEmpty())
            <x-empty-state icon="camera" category="project" title="Nog geen foto's"
                           subtitle="Foto's die je bij een stap maakt, verschijnen hier en in het projectdossier." />
        @else
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($fotos as $foto)
                    <figure class="overflow-hidden rounded-xl border border-surface-700 bg-surface-800">
                        <img src="{{ route('photos.show', $foto) }}" alt="" class="aspect-4/3 w-full object-cover">
                        <figcaption class="truncate px-2.5 py-2 text-xs text-slate-400">{{ $foto->caption }}</figcaption>
                    </figure>
                @endforeach
            </div>
        @endif
    @endif

    @if ($tab === 'meldingen')
        @include('jobs.partials.reports')
    @endif
</x-layouts.app>
