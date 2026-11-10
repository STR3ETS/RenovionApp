@php
    use App\Enums\TaskPriority;
    use App\Enums\TaskStatus;
@endphp

{{-- Alle taken van dit project, open bovenaan. --}}
@if ($tasks->isEmpty())
    <x-empty-state icon="clipboard-check" category="project" title="Geen taken"
                   subtitle="Taken ontstaan vanzelf uit meldingen, automations en gesprekken in de chat." />
@else
    <div class="overflow-hidden rounded-2xl border border-surface-700 bg-surface-800">
        @foreach ($tasks as $task)
            @php($isAf = $task->status === TaskStatus::Afgerond)
            <div class="flex flex-wrap items-center gap-3 border-b border-surface-700 p-4 last:border-b-0 {{ $isAf ? 'opacity-60' : '' }}">
                <x-signal-dot :color="! $isAf && $task->deadline?->isPast() ? 'red' : ($task->priority === TaskPriority::Hoog ? 'amber' : 'gray')" />

                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-semibold text-white {{ $isAf ? 'line-through' : '' }}">{{ $task->title }}</span>
                    <span class="block truncate text-xs text-slate-400">
                        {{ $task->owner?->name ?? 'niet toegewezen' }}@if ($task->deadline), {{ $task->deadline->translatedFormat('j M') }} @endif, {{ $task->source->label() }}
                    </span>
                    @if ($task->note)
                        <span class="mt-0.5 block text-xs text-slate-500">{{ $task->note }}</span>
                    @endif
                </span>

                <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-bold {{ $task->status->badgeClasses() }}">{{ $task->status->label() }}</span>

                @can('manage-crm')
                    @unless ($isAf)
                        <form method="POST" action="{{ route('tasks.status', $task) }}" class="shrink-0">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="{{ TaskStatus::Afgerond->value }}">
                            <button type="submit" title="Afronden"
                                    class="flex h-8 w-8 items-center justify-center rounded-full border border-surface-600 text-slate-400 transition hover:text-green-400">
                                <x-icon name="check" class="h-4 w-4" />
                            </button>
                        </form>
                    @endunless
                @endcan
            </div>
        @endforeach
    </div>
@endif
