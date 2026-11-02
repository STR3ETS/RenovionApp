@php
    /** @var array{entry: \App\Models\ScheduleEntry, job: ?\App\Models\JobExecution, status: \App\Enums\JobStatus} $row */
    $entry = $row['entry'];
    $job = $row['job'];
    $status = $row['status'];
    $project = $entry->project;
    $einde = $entry->end_date ?? $entry->date;
    $openMeldingen = $job?->openReports()->count() ?? 0;
@endphp

<a href="{{ route('jobs.show', $entry) }}"
   class="motion-lift block overflow-hidden rounded-2xl border border-surface-700 bg-surface-800 transition hover:border-surface-600">
    <span class="block h-[3px] w-full {{ $status->accentClasses() }}"></span>

    <span class="block p-4">
        <span class="mb-2 flex items-center justify-between gap-2">
            <span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $status->badgeClasses() }}">{{ $status->label() }}</span>
            <small class="text-xs text-slate-500">
                {{ $entry->date->translatedFormat('j M') }}@if (! $einde->isSameDay($entry->date)) t/m {{ $einde->translatedFormat('j M') }}@endif
                @if ($job?->dayLabel() && $einde->greaterThanOrEqualTo(today()))
                    , {{ $job->dayLabel() }}
                @endif
            </small>
        </span>

        <b class="block truncate text-sm font-bold text-white">{{ $entry->displayTitle() }}</b>
        <span class="mt-0.5 block truncate text-xs text-slate-400">{{ $project?->name }}</span>

        @if ($project?->address)
            <span class="mt-1.5 flex items-center gap-1 text-xs text-slate-500">
                <x-icon name="map-pin" class="h-3.5 w-3.5" />
                <span class="truncate">{{ collect([$project->address, $project->city])->filter()->join(', ') }}</span>
            </span>
        @endif

        @if ($job)
            <span class="mt-3 flex items-center gap-2">
                <span class="h-1.5 flex-1 overflow-hidden rounded-full bg-surface-700">
                    <span class="block h-full rounded-full bg-brand-500" style="width: {{ $job->progress() }}%"></span>
                </span>
                <small class="w-9 text-right text-xs font-bold text-slate-300">{{ $job->progress() }}%</small>
            </span>
        @endif

        @if ($openMeldingen > 0)
            <span class="mt-2 flex items-center gap-1.5 text-xs font-semibold text-amber-300">
                <x-icon name="exclamation-triangle" class="h-3.5 w-3.5" />
                {{ $openMeldingen }} {{ $openMeldingen === 1 ? 'melding' : 'meldingen' }} open
            </span>
        @endif
    </span>
</a>
