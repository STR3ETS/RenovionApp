{{-- Taakregel met oranje afvinkvak (mockup §18): afvinken = afronden. Vakje + tekst zijn samen
     de knop, zodat hij op de bouw met een duim of handschoen goed te raken is. --}}
<div class="flex items-center gap-1 pr-2">
    <button
        x-data
        @click="$refs.vak.classList.add('bg-brand-500', 'border-brand-500'); patchJson('{{ route('tasks.status', $task) }}', { status: 'afgerond' }).then(() => window.location.reload())"
        class="flex min-h-14 min-w-0 flex-1 items-center gap-3 p-3 text-left transition active:bg-gray-50"
        title="Afronden">
        <span x-ref="vak" class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border-2 border-gray-300 text-white transition">
            <x-icon name="check" class="h-4 w-4" />
        </span>
        <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-semibold text-navy-900">{{ $task->title }}</span>
            <span class="block truncate text-xs {{ $task->isOverdue() ? 'text-red-600' : 'text-gray-500' }}">
                {{ $task->project?->name ?? $task->customer?->name ?? 'Algemeen' }}@if ($task->deadline), {{ $task->isOverdue() ? 'te laat sinds '.$task->deadline->translatedFormat('j M') : ($task->deadline->isToday() ? 'vandaag' : $task->deadline->translatedFormat('D j M')) }}@endif
            </span>
        </span>
    </button>
    @if ($task->project)
        <a href="{{ route('projects.show', $task->project) }}" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-gray-300 active:bg-gray-50" title="Project openen">
            <x-icon name="chevron-right" class="h-4 w-4" />
        </a>
    @endif
</div>
