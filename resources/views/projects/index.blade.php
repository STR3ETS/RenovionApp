<x-layouts.app title="Projecten">

    <x-page-header title="Projectdossiers" subtitle="Elk project is een dossier, van aanvraag tot nazorg.">
        @if (Route::has('portfolio') && auth()->user()->can('module', 'portfolio'))
            <a href="{{ route('portfolio') }}" class="btn"><x-icon name="chart-bar" />Portfolio met stoplichten</a>
        @endif
        @can('manage-crm')
            <a href="{{ route('projects.create') }}" class="btn pri"><x-icon name="plus" />Nieuw projectdossier</a>
        @endcan
    </x-page-header>

    <div class="tabs mb-[22px]">
        <a href="{{ route('projects.index') }}"
           class="tab {{ $status === null ? 'on' : '' }}">Lopend</a>
        @foreach (\App\Enums\ProjectStatus::cases() as $statusOption)
            <a href="{{ route('projects.index', ['status' => $statusOption->value]) }}"
               class="tab {{ $status === $statusOption ? 'on' : '' }}">{{ $statusOption->label() }}</a>
        @endforeach
    </div>

    <div data-motion-fan class="grid grid-cols-1 gap-[14px] sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($projects as $project)
            <x-project-card :project="$project" :health="$health[$project->id] ?? null" />
        @empty
            <div class="sm:col-span-2 xl:col-span-3 2xl:col-span-4">
                <x-empty-state title="Geen projecten in deze weergave" subtitle="Maak een project aan of zet een aanvraag om naar een project." icon="building-office" category="project" />
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $projects->links() }}
    </div>

</x-layouts.app>
