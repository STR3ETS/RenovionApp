<x-layouts.app title="Zoeken">

    <x-page-header title="Zoeken" :subtitle="$q !== '' ? 'Resultaten voor &quot;'.$q.'&quot;' : 'Doorzoek klanten, projecten, aanvragen en taken'" />

    {{-- Zoekveld (mobiel; desktop zoekt via de topbar) --}}
    <form action="{{ route('search') }}" method="GET" class="relative mb-5 max-w-xl lg:hidden">
        <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
        <input type="search" name="q" value="{{ $q }}" autofocus placeholder="Zoek klanten, projecten, aanvragen…"
               class="w-full rounded-xl border-gray-200 bg-white py-2.5 pl-9 text-sm placeholder:text-gray-400 focus:border-brand-500 focus:ring-brand-500">
    </form>

    @if ($q === '')
        <x-empty-state title="Typ een zoekterm" subtitle="Bijvoorbeeld een klantnaam, projectnaam of plaats." />
    @elseif ($customers->isEmpty() && $projects->isEmpty() && $leads->isEmpty() && $tasks->isEmpty())
        <x-empty-state title="Niets gevonden voor &quot;{{ $q }}&quot;" subtitle="Controleer de spelling of probeer een kortere zoekterm." />
    @else
        <div class="space-y-6">
            @if ($projects->isNotEmpty())
                <section>
                    <h2 class="mb-3 text-base font-bold text-navy-900">Projecten</h2>
                    <div class="space-y-2">
                        @foreach ($projects as $project)
                            <a href="{{ route('projects.show', $project) }}" class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-3 transition hover:border-brand-400">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-navy-50 text-navy-500"><x-icon name="building-office" class="h-4.5 w-4.5" /></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold text-navy-900">{{ $project->name }}</span>
                                    <span class="block truncate text-xs text-gray-500">{{ $project->customer->name }}{{ $project->city ? ' · '.$project->city : '' }}</span>
                                </span>
                                <x-status-badge :status="$project->status" />
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($customers->isNotEmpty())
                <section>
                    <h2 class="mb-3 text-base font-bold text-navy-900">Klanten</h2>
                    <div class="space-y-2">
                        @foreach ($customers as $customer)
                            <a href="{{ route('customers.show', $customer) }}" class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-3 transition hover:border-brand-400">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-100 text-xs font-bold text-brand-700">{{ str($customer->name)->substr(0, 1)->upper() }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold text-navy-900">{{ $customer->name }}</span>
                                    <span class="block truncate text-xs text-gray-500">{{ collect([$customer->city, $customer->phone, $customer->email])->filter()->implode(' · ') ?: '—' }}</span>
                                </span>
                                <x-icon name="chevron-right" class="h-4 w-4 text-gray-300" />
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($leads->isNotEmpty())
                <section>
                    <h2 class="mb-3 text-base font-bold text-navy-900">Aanvragen</h2>
                    <div class="space-y-2">
                        @foreach ($leads as $lead)
                            <a href="{{ route('leads.show', $lead) }}" class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-3 transition hover:border-brand-400">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600"><x-icon name="inbox" class="h-4.5 w-4.5" /></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold text-navy-900">{{ $lead->customer->name }}</span>
                                    <span class="block truncate text-xs text-gray-500">{{ $lead->service ?? 'Aanvraag' }}</span>
                                </span>
                                <x-status-badge :status="$lead->status" />
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($tasks->isNotEmpty())
                <section>
                    <h2 class="mb-3 text-base font-bold text-navy-900">Taken</h2>
                    <div class="space-y-2">
                        @foreach ($tasks as $task)
                            <div class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-50 text-gray-500"><x-icon name="clipboard-check" class="h-4.5 w-4.5" /></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold text-navy-900">{{ $task->title }}</span>
                                    <span class="block truncate text-xs text-gray-500">
                                        {{ $task->project?->name ?? $task->customer?->name ?? 'Algemeen' }}
                                        @if ($task->deadline) · {{ $task->deadline->translatedFormat('j M') }} @endif
                                    </span>
                                </span>
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $task->status->badgeClasses() }}">{{ $task->status->label() }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    @endif

</x-layouts.app>
