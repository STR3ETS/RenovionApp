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
           class="tab {{ $status === null && ! $commercial ? 'on' : '' }}">Alle</a>
        @can('manage-crm')
            <a href="{{ route('projects.index', ['fase' => 'commercieel']) }}"
               class="tab {{ $commercial ? 'on' : '' }}">Commercieel</a>
        @endcan
        @foreach (\App\Enums\ProjectStatus::cases() as $statusOption)
            <a href="{{ route('projects.index', ['status' => $statusOption->value]) }}"
               class="tab {{ $status === $statusOption ? 'on' : '' }}">{{ $statusOption->label() }}</a>
        @endforeach
    </div>

    <div data-motion-fan class="grid grid-cols-1 gap-[14px] sm:grid-cols-2 xl:grid-cols-3">
        {{-- Dossiers die nog in de verkoop zitten: aanvraag, intake, begroting of offerte --}}
        @foreach ($dossiers as $lead)
            @php
                $dossier = \App\Support\Dossier::forLead($lead);
                $initials = $lead->assignee ? str($lead->assignee->name)->explode(' ')->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('') : null;
            @endphp
            <a href="{{ $dossier->quote ? route('quotes.show', $dossier->quote) : route('leads.show', $lead) }}" class="pcard">
                <div class="flex items-center justify-between gap-2">
                    <span class="id">{{ $dossier->number() }}</span>
                    <span class="pill vio"><span class="d"></span>{{ $dossier->stageLabel() }}</span>
                </div>
                <div class="min-w-0">
                    <h4 class="truncate">{{ $lead->customer->name }}</h4>
                    <span class="block truncate text-muted">{{ collect([$lead->service, $lead->customer->city])->filter()->implode(', ') ?: 'Aanvraag' }}</span>
                </div>
                <div class="flex items-center justify-between gap-2">
                    <span class="truncate text-muted">{{ $lead->service ?: 'Aanvraag' }}</span>
                    <b class="shrink-0 tabular-nums">{{ $dossier->quote ? '€ '.number_format((float) $dossier->quote->subtotal, 0, ',', '.') : 'nog geen prijs' }}</b>
                </div>
                @if ($lead->assignee)
                    <div class="flex items-center gap-2 text-xs">
                        <span class="av sm" title="{{ $lead->assignee->name }}">{{ $initials }}</span>
                        <span class="truncate text-muted">{{ $lead->assignee->name }}</span>
                    </div>
                @endif
            </a>
        @endforeach

        @forelse ($projects as $project)
            <x-project-card :project="$project" :health="$health[$project->id] ?? null" />
        @empty
            @if ($dossiers->isEmpty())
                <div class="sm:col-span-2 xl:col-span-3">
                    <x-empty-state title="Geen dossiers in deze weergave" subtitle="Maak een project aan of leg een nieuwe aanvraag vast." icon="building-office" category="project" />
                </div>
            @endif
        @endforelse
    </div>

    <div class="mt-4">
        {{ $projects->links() }}
    </div>

</x-layouts.app>
