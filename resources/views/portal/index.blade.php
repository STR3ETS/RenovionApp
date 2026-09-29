<x-layouts.portal title="Uw projecten">

    <h1 class="text-xl font-bold text-navy-950">Welkom {{ str(auth()->user()->name)->before(' ') }}</h1>
    <p class="mt-1 mb-5 text-sm text-gray-500">Volg hier de voortgang van uw {{ $projects->count() === 1 ? 'project' : 'projecten' }}.</p>

    <div class="space-y-3">
        @forelse ($projects as $project)
            <a href="{{ route('portal.show', $project) }}" class="flex items-center gap-4 rounded-2xl border border-gray-200 bg-white p-4 transition hover:border-brand-400">
                <span class="h-16 w-20 shrink-0 overflow-hidden rounded-xl bg-gradient-to-br from-navy-700 to-navy-950">
                    @if ($project->cover_photo_path || ($project->photos_count ?? 0) > 0)
                        <img src="{{ route('projects.cover', $project) }}" alt="{{ $project->name }}" class="h-full w-full object-cover">
                    @endif
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-bold text-navy-900">{{ $project->name }}</span>
                    <span class="mt-1.5 block h-1.5 overflow-hidden rounded-full bg-gray-100">
                        <span class="block h-full rounded-full bg-brand-500" style="width: {{ $project->progress }}%"></span>
                    </span>
                    <span class="mt-1 block text-xs text-gray-400">{{ $project->progress }}% gereed</span>
                </span>
                <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-gray-300" />
            </a>
        @empty
            <div class="rounded-2xl border border-gray-200 bg-white p-8 text-center text-sm text-gray-400">
                Er zijn nog geen projecten aan uw account gekoppeld.
            </div>
        @endforelse
    </div>

</x-layouts.portal>
