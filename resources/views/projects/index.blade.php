<x-layouts.app title="Projecten">

    <x-page-header title="Projecten" subtitle="{{ $projects->total() }} projecten">
        @can('manage-crm')
            <a href="{{ route('projects.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-500">+ Project</a>
        @endcan
    </x-page-header>

    <div class="mb-4 flex flex-wrap gap-1.5">
        <a href="{{ route('projects.index') }}"
           class="rounded-full px-3 py-1.5 text-xs font-semibold {{ $status === null ? 'bg-navy-900 text-white' : 'bg-white text-gray-600 border border-gray-300' }}">Lopend</a>
        @foreach (\App\Enums\ProjectStatus::cases() as $statusOption)
            <a href="{{ route('projects.index', ['status' => $statusOption->value]) }}"
               class="rounded-full px-3 py-1.5 text-xs font-semibold {{ $status === $statusOption ? 'bg-navy-900 text-white' : 'bg-white text-gray-600 border border-gray-300' }}">{{ $statusOption->label() }}</a>
        @endforeach
    </div>

    <div class="space-y-2">
        @forelse ($projects as $project)
            <a href="{{ route('projects.show', $project) }}" class="block rounded-xl border bg-white p-3 transition hover:border-brand-400 {{ $project->isOverdue() ? 'border-red-300' : 'border-gray-200' }}">
                <div class="flex items-center gap-3">
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-navy-900">{{ $project->name }}</span>
                        <span class="block text-xs text-gray-500">
                            {{ $project->customer->name }} · {{ $project->city ?? '—' }}
                            · € {{ number_format((float) $project->value, 0, ',', '.') }}
                        </span>
                    </span>
                    @if ($project->isOverdue())
                        <x-signal-dot color="red" title="Verwachte einddatum verstreken" />
                    @endif
                    @if ($project->depositOutstanding())
                        <x-icon name="currency-euro" class="text-amber-500" title="Aanbetaling niet ontvangen" />
                    @endif
                    <x-status-badge :status="$project->status" />
                </div>
                @if ($project->status === \App\Enums\ProjectStatus::Uitvoering)
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-100">
                        <div class="h-full rounded-full bg-brand-500" style="width: {{ $project->progress }}%"></div>
                    </div>
                @endif
            </a>
        @empty
            <x-empty-state title="Geen projecten in deze weergave" subtitle="Projecten ontstaan automatisch wanneer een offerte akkoord is." />
        @endforelse
    </div>

    <div class="mt-4">
        {{ $projects->links() }}
    </div>

</x-layouts.app>
