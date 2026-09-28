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

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
        @forelse ($projects as $project)
            <x-project-card :project="$project" />
        @empty
            <div class="sm:col-span-2 xl:col-span-3 2xl:col-span-4">
                <x-empty-state title="Geen projecten in deze weergave" subtitle="Maak een project aan of zet een aanvraag om naar een project." />
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $projects->links() }}
    </div>

</x-layouts.app>
