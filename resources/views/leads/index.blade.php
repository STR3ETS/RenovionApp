<x-layouts.app title="Aanvragen">

    <x-page-header title="Aanvragen & Sales" subtitle="{{ $leads->filter(fn ($lead) => $lead->status->isOpen())->count() }} open aanvragen">
        <div class="flex rounded-lg border border-gray-300 bg-white p-0.5 text-xs font-semibold">
            <a href="{{ route('leads.index', ['view' => 'kanban']) }}"
               class="rounded-md px-3 py-1.5 {{ $view === 'kanban' ? 'bg-navy-900 text-white' : 'text-gray-600' }}">Kanban</a>
            <a href="{{ route('leads.index', ['view' => 'lijst']) }}"
               class="rounded-md px-3 py-1.5 {{ $view === 'lijst' ? 'bg-navy-900 text-white' : 'text-gray-600' }}">Lijst</a>
        </div>
        <a href="{{ route('leads.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-500">+ Aanvraag</a>
    </x-page-header>

    @if ($view === 'kanban')
        {{-- Kanban: horizontaal scrollbaar, drag & drop --}}
        <div class="-mx-4 overflow-x-auto px-4 pb-4 lg:mx-0 lg:px-0"
             x-data="{
                 dragging: null,
                 async drop(status) {
                     if (!this.dragging) return;
                     await patchJson(this.dragging.url, { status });
                     window.location.reload();
                 }
             }">
            <div class="flex min-w-max gap-3">
                @foreach (\App\Enums\LeadStatus::pipeline() as $statusColumn)
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
                                <div class="rounded-lg border border-dashed border-gray-300 py-6 text-center text-xs text-gray-400">Leeg</div>
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
                <a href="{{ route('leads.show', $lead) }}" class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-3 transition hover:border-brand-400">
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-navy-900">{{ $lead->customer->name }}</span>
                        <span class="block truncate text-xs text-gray-500">
                            {{ $lead->customer->city ?? '—' }} · {{ $lead->service ?? 'Aanvraag' }}
                            @if ($lead->value) · € {{ number_format((float) $lead->value, 0, ',', '.') }} @endif
                        </span>
                        <span class="mt-0.5 block text-xs text-gray-400">
                            {{ $lead->assignee?->name ?? 'Niet toegewezen' }}
                            · laatste contact: {{ $lead->last_contact_at?->translatedFormat('j M') ?? 'nog geen' }}
                        </span>
                    </span>
                    @if ($lead->missingPhone())
                        <x-signal-dot color="amber" title="Geen telefoonnummer" />
                    @endif
                    <x-status-badge :status="$lead->status" />
                </a>
            @empty
                <x-empty-state title="Nog geen open aanvragen">
                    <a href="{{ route('leads.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">+ Eerste aanvraag</a>
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
                                <span class="block text-xs text-gray-500">{{ $lead->service ?? 'Aanvraag' }} @if ($lead->lost_reason) · {{ $lead->lost_reason }} @endif</span>
                            </span>
                            <x-status-badge :status="$lead->status" />
                        </a>
                    @endforeach
                </div>
            </details>
        @endif
    @endif

</x-layouts.app>
