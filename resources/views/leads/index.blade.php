<x-layouts.app title="Aanvragen">

    <x-page-header eyebrow="Sales" category="lead" title="Aanvragen & Sales" subtitle="{{ \App\Support\Tally::of($leads->filter(fn ($lead) => $lead->status->isOpen())->count(), 'open aanvraag', 'open aanvragen') }}">
        <div class="flex rounded-lg border border-gray-300 bg-white p-0.5 text-xs font-semibold">
            <a href="{{ route('leads.index', ['view' => 'kanban']) }}"
               class="tap rounded-md px-3 py-1.5 {{ $view === 'kanban' ? 'bg-navy-900 text-white' : 'text-gray-600' }}">Kanban</a>
            <a href="{{ route('leads.index', ['view' => 'lijst']) }}"
               class="tap rounded-md px-3 py-1.5 {{ $view === 'lijst' ? 'bg-navy-900 text-white' : 'text-gray-600' }}">Lijst</a>
        </div>
        <a href="{{ route('leads.create') }}" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-bold text-white transition hover:bg-brand-600 active:bg-brand-600"><x-app-label web="+ Aanvraag" app="Nieuwe aanvraag" /></a>
    </x-page-header>

    @if ($view === 'kanban')
        @php
            $pipeline = \App\Enums\LeadStatus::pipeline();
            // Mobiel opent op de eerste status met aanvragen (meestal "Nieuw").
            $startStatus = collect($pipeline)->first(fn ($status) => $columns[$status->value]->isNotEmpty()) ?? $pipeline[0];
        @endphp

        @nativeapp
            {{-- iOS/Android-app (prototype Renovion OS): kerncijfers en de kolommen onder elkaar --}}
            @php $open = $leads->filter(fn ($lead) => $lead->status->isOpen()); @endphp
            <div class="app-kpis mb-4 lg:hidden">
                <div class="app-kpi" style="--kc: #c2506d">
                    <span class="lbl">Open leads</span>
                    <span class="v" data-count>{{ $open->count() }}</span>
                </div>
                <div class="app-kpi" style="--kc: #3e6ba8">
                    <span class="lbl">Potentiële omzet</span>
                    <span class="v">€ {{ number_format((float) $open->sum('value'), 0, ',', '.') }}</span>
                </div>
            </div>

            <div class="space-y-3 lg:hidden">
                @foreach ($pipeline as $statusColumn)
                    <section class="app-kcol">
                        <div class="mb-2.5 flex items-center justify-between px-1">
                            <h2 class="text-[15px] font-semibold text-navy-900">{{ $statusColumn->label() }}</h2>
                            <span class="rounded-full border border-gray-200 bg-white px-2 py-0.5 text-xs font-bold text-gray-600">{{ $columns[$statusColumn->value]->count() }}</span>
                        </div>
                        <div class="space-y-2">
                            @forelse ($columns[$statusColumn->value] as $lead)
                                <x-lead-card :lead="$lead" />
                            @empty
                                <p class="px-1 text-sm text-gray-500">Geen aanvragen</p>
                            @endforelse
                        </div>
                    </section>
                @endforeach
            </div>
        @else
        {{-- Mobiel: statusbalk (zoals de dagbalk in Planning) + alleen de kaarten van de gekozen status --}}
        <div class="lg:hidden" x-data="{ status: @js($startStatus->value) }">
            {{-- scroll-px-4: snap houdt de 16px marge aan, anders springt de eerste knop tegen de schermrand.
                 De spacer aan het eind doet hetzelfde voor de laatste knop (padding-right telt niet mee bij scrollen). --}}
            <div data-pill class="-mx-4 mb-4 flex snap-x scroll-px-4 gap-1.5 overflow-x-auto px-4 pb-1">
                @foreach ($pipeline as $statusColumn)
                    <button type="button" @click="status = @js($statusColumn->value)"
                            :class="status === @js($statusColumn->value) ? 'border-navy-950 bg-navy-950 text-white' : 'border-gray-200 bg-white text-navy-900'"
                            class="flex shrink-0 snap-start items-center gap-1.5 rounded-xl border px-3 py-2 text-sm font-semibold transition">
                        {{ $statusColumn->label() }}
                        <span :class="status === @js($statusColumn->value) ? 'bg-white/20 text-white' : 'bg-lead-100 text-lead-700'"
                              class="rounded-full px-1.5 py-0.5 text-[11px] leading-none font-bold">{{ $columns[$statusColumn->value]->count() }}</span>
                    </button>
                @endforeach
                <span class="w-2.5 shrink-0" aria-hidden="true"></span>
            </div>

            @foreach ($pipeline as $statusColumn)
                <section x-show="status === @js($statusColumn->value)" @if ($statusColumn !== $startStatus) x-cloak @endif class="space-y-2">
                    @forelse ($columns[$statusColumn->value] as $lead)
                        <x-lead-card :lead="$lead" />
                    @empty
                        <div class="rounded-2xl border border-dashed border-gray-300 bg-white py-8 text-center text-sm text-gray-500">Geen aanvragen met status {{ strtolower($statusColumn->label()) }}</div>
                    @endforelse
                </section>
            @endforeach
        </div>
        @endnativeapp

        {{-- Desktop: Kanban, horizontaal scrollbaar, drag & drop --}}
        <div class="-mx-4 hidden overflow-x-auto px-4 pb-4 lg:mx-0 lg:block lg:px-0"
             x-data="{
                 dragging: null,
                 async drop(status) {
                     if (!this.dragging) return;
                     await patchJson(this.dragging.url, { status });
                     window.location.reload();
                 }
             }">
            <div class="flex min-w-max gap-3">
                @foreach ($pipeline as $statusColumn)
                    <div class="w-72 shrink-0 rounded-xl bg-gray-200/70 p-2"
                         @dragover.prevent
                         @drop="drop('{{ $statusColumn->value }}')">
                        <div class="mb-2 flex items-center justify-between px-1">
                            <span class="text-xs font-bold tracking-wide text-gray-600 uppercase">{{ $statusColumn->label() }}</span>
                            <span class="rounded-full bg-white px-2 py-0.5 text-xs font-bold text-gray-500">{{ $columns[$statusColumn->value]->count() }}</span>
                        </div>
                        <div class="space-y-2">
                            @foreach ($columns[$statusColumn->value] as $lead)
                                <div draggable="true"
                                     @dragstart="dragging = { url: '{{ route('leads.status', $lead) }}' }"
                                     @dragend="dragging = null"
                                     class="cursor-grab active:cursor-grabbing">
                                    <x-lead-card :lead="$lead" />
                                </div>
                            @endforeach
                            @if ($columns[$statusColumn->value]->isEmpty())
                                <div class="rounded-lg border border-dashed border-gray-300 py-6 text-center text-xs text-gray-500">Leeg</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        {{-- Lijstweergave --}}
        <div class="space-y-2">
            @forelse ($leads->filter(fn ($lead) => $lead->status->isOpen()) as $lead)
                <a href="{{ route('leads.show', $lead) }}" class="flex items-center gap-3 rounded-xl border border-gray-200 border-l-4 border-l-lead-500 bg-white p-3 transition hover:shadow-sm">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-lead-100 text-lead-700"><x-icon name="inbox" class="h-4 w-4" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-navy-900">{{ $lead->customer->name }}</span>
                        <span class="block truncate text-xs text-gray-500">
                            {{ $lead->customer->city ?? '-' }}, {{ $lead->service ?? 'Aanvraag' }}@if ($lead->value), € {{ number_format((float) $lead->value, 0, ',', '.') }}@endif
                        </span>
                        <span class="mt-0.5 block text-xs text-gray-500">
                            {{ $lead->assignee?->name ?? 'Niet toegewezen' }}, laatste contact: {{ $lead->last_contact_at?->translatedFormat('j M') ?? 'nog geen' }}
                        </span>
                    </span>
                    @if ($lead->missingPhone())
                        <x-signal-dot color="amber" title="Geen telefoonnummer" />
                    @endif
                    @if ($lead->qualification !== \App\Enums\LeadQualification::Onbeoordeeld)
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $lead->qualification->badgeClasses() }}">{{ $lead->qualification->label() }}</span>
                    @endif
                    <x-status-badge :status="$lead->status" />
                </a>
            @empty
                <x-empty-state title="Nog geen open aanvragen" icon="inbox" category="lead">
                    <a href="{{ route('leads.create') }}" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-bold text-white">+ Eerste aanvraag</a>
                </x-empty-state>
            @endforelse
        </div>

        @if ($inactief->isNotEmpty())
            <details class="mt-6">
                <summary class="cursor-pointer text-sm font-semibold text-gray-500">Verloren / on hold ({{ $inactief->count() }})</summary>
                <div class="mt-2 space-y-2">
                    @foreach ($inactief as $lead)
                        <a href="{{ route('leads.show', $lead) }}" class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-3 opacity-70 transition hover:opacity-100">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-navy-900">{{ $lead->customer->name }}</span>
                                <span class="block text-xs text-gray-500">{{ $lead->service ?? 'Aanvraag' }} @if ($lead->lost_reason), {{ $lead->lost_reason }} @endif</span>
                            </span>
                            <x-status-badge :status="$lead->status" />
                        </a>
                    @endforeach
                </div>
            </details>
        @endif
    @endif

</x-layouts.app>
