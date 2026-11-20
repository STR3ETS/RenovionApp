{{-- Het overzicht: bovenaan de projectcockpit uit het prototype (stoplichten,
     mijlpalen, blokkades, budget). De overige gegevens staan eronder, ingeklapt. --}}
@can('manage-crm')
    @include('projects.partials.cockpit')

    <details class="pd-more" x-data="{ open: false }" x-init="try { open = localStorage.getItem('dossier-gegevens') === '1' } catch (e) {}; $el.open = open" @toggle="try { localStorage.setItem('dossier-gegevens', $el.open ? '1' : '0') } catch (e) {}">
        <summary>
            <x-icon name="folder" />
            <span><b>Projectgegevens</b><small>Klant, taken, planning, foto's, oplevering en activiteit</small></span>
            <x-icon name="chevron-down" />
        </summary>
@endcan

<div class="space-y-4">
    @cannot('manage-crm')
        <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
            <div class="mb-2 flex items-center justify-between">
                <h2 class="text-sm font-bold text-ink">Projectvoortgang</h2>
                <span class="text-xs font-semibold text-faint">{{ $project->progress }}%</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-surface-750">
                <div class="h-full rounded-full bg-brand-500" style="width: {{ $project->progress }}%"></div>
            </div>
        </section>
    @endcannot

    @if ($project->end_date_expected?->isPast() && $project->end_date_actual === null)
        <div class="flex items-center gap-2.5 rounded-2xl border border-amber-500/25 bg-amber-500/10 px-4 py-3">
            <x-signal-dot color="amber" />
            <p class="text-sm text-ink">
                Verwachte opleverdatum ({{ $project->end_date_expected->translatedFormat('j M') }}) is verstreken.
            </p>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            {{-- Wat er aan werk open staat --}}
            <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="text-sm font-bold text-ink">Open <span class="text-brand-500">taken</span></h2>
                    <a href="{{ route('projects.show', [$project, 'tab' => 'taken']) }}" class="text-xs font-semibold text-muted transition hover:text-ink">Alle taken</a>
                </div>

                @forelse ($tasks as $task)
                    <div class="flex items-center gap-3 border-b border-surface-700 py-2.5 last:border-b-0">
                        <x-signal-dot :color="$task->deadline?->isPast() ? 'red' : 'gray'" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm text-ink">{{ $task->title }}</span>
                            <span class="block text-xs text-faint">
                                {{ $task->owner?->name ?? 'niet toegewezen' }}@if ($task->deadline), {{ $task->deadline->translatedFormat('j M') }} @endif
                            </span>
                        </span>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $task->status->badgeClasses() }}">{{ $task->status->label() }}</span>
                    </div>
                @empty
                    <p class="text-sm text-faint">Geen open taken op dit project.</p>
                @endforelse
            </section>

            {{-- Komende planning --}}
            <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="text-sm font-bold text-ink">Komende <span class="text-brand-500">planning</span></h2>
                    <a href="{{ route('projects.show', [$project, 'tab' => 'planning']) }}" class="text-xs font-semibold text-muted transition hover:text-ink">Hele planning</a>
                </div>

                @forelse ($entries as $entry)
                    <div class="flex items-center gap-3 border-b border-surface-700 py-2.5 last:border-b-0">
                        <span class="w-16 shrink-0 text-xs font-bold text-ink2">{{ $entry->date->translatedFormat('j M') }}</span>
                        <span class="min-w-0 flex-1 truncate text-sm text-ink">{{ $entry->displayTitle() }}</span>
                        <span class="shrink-0 text-xs text-faint">{{ $entry->user?->name }}</span>
                    </div>
                @empty
                    <p class="text-sm text-faint">Niets ingepland vanaf vandaag.</p>
                @endforelse
            </section>

            {{-- Laatste foto's --}}
            @if ($photos->isNotEmpty())
                <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h2 class="text-sm font-bold text-ink">Laatste <span class="text-brand-500">foto's</span></h2>
                        <a href="{{ route('projects.show', [$project, 'tab' => 'fotos']) }}" class="text-xs font-semibold text-muted transition hover:text-ink">Alle foto's</a>
                    </div>
                    <div class="grid grid-cols-3 gap-2 sm:grid-cols-6">
                        @foreach ($photos as $photo)
                            <img src="{{ route('photos.show', $photo) }}" alt="{{ $photo->caption }}" loading="lazy"
                                 class="aspect-square w-full rounded-lg object-cover" title="{{ $photo->caption }}">
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        <div class="space-y-4">
            {{-- Projectinfo --}}
            <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-3 text-sm font-bold text-ink">Project<span class="text-brand-500">info</span></h2>
                <dl class="space-y-2.5 text-sm">
                    <div>
                        <dt class="text-xs text-faint">Klant</dt>
                        <dd><a href="{{ route('customers.show', $project->customer) }}" class="font-semibold text-klant-300 transition hover:text-klant-200">{{ $project->customer->name }}</a></dd>
                    </div>
                    <div>
                        <dt class="text-xs text-faint">Adres</dt>
                        <dd class="text-ink">{{ collect([$project->address, $project->city])->filter()->join(', ') ?: 'onbekend' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-faint">Looptijd</dt>
                        <dd class="text-ink">
                            {{ $project->start_date?->translatedFormat('j M Y') ?? 'geen start' }}
                            tot {{ $project->end_date_actual?->translatedFormat('j M Y') ?? $project->end_date_expected?->translatedFormat('j M Y') ?? 'onbekend' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-faint">Uitvoerders</dt>
                        <dd class="text-ink">{{ $project->craftsmen->pluck('name')->join(', ') ?: 'nog niemand toegewezen' }}</dd>
                    </div>
                    @if ($project->end_date_actual)
                        <div>
                            <dt class="text-xs text-faint">Garantie</dt>
                            <dd class="text-ink">
                                {{ $project->warranty_months }} maanden, tot
                                {{ $project->end_date_actual->copy()->addMonths($project->warranty_months)->translatedFormat('j M Y') }}
                            </dd>
                        </div>
                    @endif
                </dl>

                @can('manage-crm')
                    <form method="POST" action="{{ route('projects.status', $project) }}" class="mt-4 border-t border-surface-700 pt-4">
                        @csrf
                        @method('PATCH')
                        <label for="status" class="mb-1.5 block text-xs text-faint">Status</label>
                        <select name="status" id="status" onchange="this.form.submit()"
                                class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-ink focus:border-brand-500 focus:ring-brand-500">
                            @foreach (\App\Enums\ProjectStatus::cases() as $statusOption)
                                <option value="{{ $statusOption->value }}" @selected($project->status === $statusOption)>{{ $statusOption->label() }}</option>
                            @endforeach
                        </select>
                    </form>
                @endcan
            </section>

            {{-- Opleverrapport --}}
            @can('manage-crm')
                <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                    <h2 class="mb-2 text-sm font-bold text-ink">Oplever<span class="text-brand-500">rapport</span></h2>

                    @if ($deliveryReport)
                        <p class="mb-3 text-xs text-muted">
                            Opgemaakt {{ $deliveryReport->generated_at->translatedFormat('j M Y') }}.
                            {{ $deliveryReport->client_signed_at ? 'Getekend door de klant.' : 'Nog niet getekend door de klant.' }}
                        </p>
                        <a href="{{ route('delivery-reports.show', $deliveryReport) }}"
                           class="block w-full rounded-xl border border-surface-600 py-2.5 text-center text-sm font-semibold text-ink transition hover:text-ink">
                            Rapport openen
                        </a>
                    @else
                        <p class="mb-3 text-xs text-muted">Nog geen rapport. Dat bevriest de scope, het bewijs en de restpunten.</p>
                        <form method="POST" action="{{ route('delivery-reports.store', $project) }}">
                            @csrf
                            <button type="submit" class="btn pri w-full">
                                Concept opmaken
                            </button>
                        </form>
                    @endif
                </section>
            @endcan

            {{-- Laatste activiteiten --}}
            <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="text-sm font-bold text-ink">Laatste <span class="text-brand-500">activiteit</span></h2>
                    <a href="{{ route('projects.show', [$project, 'tab' => 'activiteiten']) }}" class="text-xs font-semibold text-muted transition hover:text-ink">Alles</a>
                </div>

                @forelse ($events as $event)
                    <div class="border-b border-surface-700 py-2 last:border-b-0">
                        <p class="text-sm text-ink">{{ $event->title }}</p>
                        <p class="text-xs text-faint">{{ $event->happened_at->translatedFormat('j M H:i') }}, {{ $event->source->label() }}</p>
                    </div>
                @empty
                    <p class="text-sm text-faint">Nog niets vastgelegd.</p>
                @endforelse
            </section>
        </div>
    </div>
</div>

@can('manage-crm')
    </details>
@endcan
