@php
    use App\Models\ServiceTicket;
@endphp

<x-layouts.app title="Nazorg en service">
    <x-page-header title="Nazorg en service" eyebrow="Projecten" category="project"
                   subtitle="Meldingen na de oplevering. De eerste vraag is altijd of het onder garantie valt." />

    <x-kpi-row>
        <x-kpi dark label="Open meldingen" :value="(string) $openCount" sub="wachten op afhandeling" />
        <x-kpi label="Spoed" :value="(string) $urgent" :alert="$urgent > 0"
               :sub="$urgent > 0 ? 'lekkage of onveilig' : 'niets dringend'" />
        <x-kpi label="Langst open" :value="$oldest.' '.($oldest === 1 ? 'dag' : 'dagen')" sub="oudste melding" />
        <x-kpi label="Afgehandeld" :value="(string) ($counts[ServiceTicket::STATUS_RESOLVED] ?? 0)" sub="in totaal" />
    </x-kpi-row>

    <div class="grid gap-4 xl:grid-cols-3">
        <div class="xl:col-span-2">
            <x-tabs>
                <x-tab :href="route('service.index')" :active="$filter === ''" :count="$counts->sum()">Alles</x-tab>
                @foreach (ServiceTicket::STATUSES as $key => $label)
                    @continue(($counts[$key] ?? 0) === 0 && $filter !== $key)
                    <x-tab :href="route('service.index', ['status' => $key])"
                           :active="$filter === $key" :count="$counts[$key] ?? 0">{{ $label }}</x-tab>
                @endforeach
            </x-tabs>

            @if ($tickets->isEmpty())
                <x-empty-state icon="wrench" category="project" title="Geen servicemeldingen"
                               subtitle="Zodra een klant na de oplevering iets meldt, staat het hier." />
            @else
                <div class="space-y-3">
                    @foreach ($tickets as $ticket)
                        <div class="overflow-hidden rounded-2xl border bg-surface-800 {{ $ticket->priority === 'spoed' && $ticket->isOpen() ? 'border-red-500/30' : 'border-surface-700' }}">
                            <div class="p-4">
                                <div class="flex flex-wrap items-start gap-3">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $ticket->priority === 'spoed' ? 'bg-red-500/15 text-red-400' : 'bg-project-500/15 text-project-400' }}">
                                        <x-icon name="wrench" class="h-5 w-5" />
                                    </span>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <b class="truncate text-sm font-bold text-white">{{ $ticket->title }}</b>
                                            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $ticket->badgeClasses() }}">{{ $ticket->statusLabel() }}</span>
                                            @if ($ticket->priority === 'spoed')
                                                <span class="rounded-full bg-red-500/15 px-2 py-0.5 text-[10px] font-bold text-red-300">Spoed</span>
                                            @endif
                                            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $ticket->warranty ? 'bg-green-500/15 text-green-300' : 'bg-amber-500/15 text-amber-300' }}">
                                                {{ $ticket->warranty ? 'Onder garantie' : 'Buiten garantie' }}
                                            </span>
                                        </div>

                                        <p class="mt-1 text-sm text-slate-300">{{ $ticket->description }}</p>

                                        <p class="mt-1.5 text-xs text-slate-500">
                                            {{ $ticket->number }}, {{ $ticket->customer->name }}@if ($ticket->project), {{ $ticket->project->name }} @endif,
                                            {{ ServiceTicket::CHANNELS[$ticket->reported_via] ?? $ticket->reported_via }},
                                            {{ $ticket->daysOpen() }} {{ $ticket->daysOpen() === 1 ? 'dag' : 'dagen' }}
                                            {{ $ticket->isOpen() ? 'open' : 'doorlooptijd' }}
                                            @if ($ticket->assignee)
                                                , {{ $ticket->assignee->name }}
                                            @endif
                                        </p>

                                        @if ($ticket->warranty_note)
                                            <p class="mt-1 text-xs {{ $ticket->warranty ? 'text-slate-500' : 'text-amber-300' }}">{{ $ticket->warranty_note }}</p>
                                        @endif

                                        @if ($ticket->resolution)
                                            <p class="mt-2 rounded-lg bg-surface-850 p-3 text-xs text-slate-300">{{ $ticket->resolution }}</p>
                                        @endif
                                    </div>
                                </div>

                                @can('manage-crm')
                                    @if ($ticket->isOpen())
                                        <form method="POST" action="{{ route('service.update', $ticket) }}" class="mt-3 space-y-2 border-t border-surface-700 pt-3">
                                            @csrf
                                            @method('PATCH')

                                            <div class="grid gap-2 sm:grid-cols-3">
                                                <select name="status" class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                                                    @foreach (ServiceTicket::STATUSES as $key => $label)
                                                        <option value="{{ $key }}" @selected($ticket->status === $key)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                <select name="assigned_to" class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                                                    <option value="">Niemand toegewezen</option>
                                                    @foreach ($people as $person)
                                                        <option value="{{ $person->id }}" @selected($ticket->assigned_to === $person->id)>{{ $person->name }}</option>
                                                    @endforeach
                                                </select>
                                                <input type="date" name="planned_on" value="{{ $ticket->planned_on?->toDateString() }}" title="Gepland op"
                                                       class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                                            </div>

                                            <div class="flex flex-wrap items-center gap-2">
                                                <input type="text" name="resolution" value="{{ $ticket->resolution }}" placeholder="Wat is er gedaan of afgesproken?"
                                                       class="min-w-0 flex-1 rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">
                                                <label class="flex shrink-0 items-center gap-2 text-sm text-slate-300">
                                                    <input type="checkbox" name="warranty" value="1" @checked($ticket->warranty)
                                                           class="rounded border-surface-600 bg-surface-750 text-brand-500 focus:ring-brand-500">
                                                    Garantie
                                                </label>
                                                <button type="submit" class="shrink-0 rounded-xl bg-brand-500 px-4 py-2 text-sm font-bold text-white transition hover:bg-brand-600">Opslaan</button>
                                            </div>
                                        </form>
                                    @endif
                                @endcan
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Melding aannemen --}}
        <div>
            <form method="POST" action="{{ route('service.store') }}" class="space-y-3 rounded-2xl border border-surface-700 bg-surface-800 p-5">
                @csrf
                <h2 class="text-sm font-bold text-white">Melding <span class="text-brand-500">aannemen</span></h2>
                <p class="text-xs text-slate-400">Het systeem rekent zelf uit of het nog binnen de garantietermijn valt.</p>

                <x-field label="Project" name="project_id">
                    <select name="project_id" id="project_id"
                            class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                        <option value="">Geen project, alleen een klant</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}">{{ $project->name }}{{ $project->customer ? ', '.$project->customer->name : '' }}</option>
                        @endforeach
                    </select>
                </x-field>

                <x-field label="Of een klant" name="customer_id">
                    <select name="customer_id" id="customer_id"
                            class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                        <option value="">Kies een klant</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </x-field>

                <x-field label="Wat is er aan de hand?" name="title">
                    <input type="text" name="title" id="title" required
                           class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                </x-field>

                <x-field label="Toelichting" name="description">
                    <textarea name="description" id="description" rows="3" required
                              class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white focus:border-brand-500 focus:ring-brand-500"></textarea>
                </x-field>

                <div class="grid gap-2 sm:grid-cols-2">
                    <x-field label="Prioriteit" name="priority">
                        <select name="priority" id="priority" required
                                class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                            @foreach (ServiceTicket::PRIORITIES as $key => $label)
                                <option value="{{ $key }}" @selected($key === 'normaal')>{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-field>
                    <x-field label="Hoe binnengekomen" name="reported_via">
                        <select name="reported_via" id="reported_via" required
                                class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                            @foreach (ServiceTicket::CHANNELS as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-field>
                </div>

                <button type="submit" class="w-full rounded-xl bg-brand-500 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600">Aannemen</button>
            </form>
        </div>
    </div>
</x-layouts.app>
