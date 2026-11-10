{{-- Het overzicht: de cockpit uit hoofdstuk 7 met de vier stoplichten, en
     daaronder de korte lijstjes die je vanaf een dossier wilt kunnen zien. --}}
<div class="space-y-4">
    @can('manage-crm')
        <x-health-lights :health="$health" />

        <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
            <h2 class="mb-4 text-sm font-bold text-white">Project<span class="text-brand-500">voortgang</span></h2>

            <div class="grid gap-5 lg:grid-cols-3">
                <x-progress-vs-plan :health="$health" />

                <div>
                    <p class="text-xs font-semibold text-slate-400">Volgende mijlpaal</p>
                    <p class="mt-1 text-sm font-bold text-white">{{ $health->nextMilestone ?? 'Alle fasen gereed' }}</p>
                    <p class="text-xs text-slate-500">{{ $health->nextMilestoneDue ?? 'geen datum gepland' }}</p>
                </div>

                <div>
                    <p class="text-xs font-semibold text-slate-400">Acties en blokkades</p>
                    @forelse ($health->attentionSignals() as $signal)
                        <p class="mt-1 flex items-start gap-1.5 text-xs leading-relaxed {{ $signal->light->textClasses() }}">
                            <x-signal-dot :color="$signal->light->dotColor()" class="mt-1" />
                            <span><span class="font-semibold">{{ $signal->label }}:</span> {{ $signal->reason }}</span>
                        </p>
                    @empty
                        <p class="mt-1 text-xs text-slate-500">Niets dat aandacht vraagt.</p>
                    @endforelse
                </div>
            </div>
        </section>
    @else
        <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
            <div class="mb-2 flex items-center justify-between">
                <h2 class="text-sm font-bold text-white">Projectvoortgang</h2>
                <span class="text-xs font-semibold text-slate-500">{{ $project->progress }}%</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-surface-750">
                <div class="h-full rounded-full bg-brand-500" style="width: {{ $project->progress }}%"></div>
            </div>
        </section>
    @endcan

    @if ($project->end_date_expected?->isPast() && $project->end_date_actual === null)
        <div class="flex items-center gap-2.5 rounded-2xl border border-amber-500/25 bg-amber-500/10 px-4 py-3">
            <x-signal-dot color="amber" />
            <p class="text-sm text-slate-200">
                Verwachte opleverdatum ({{ $project->end_date_expected->translatedFormat('j M') }}) is verstreken.
            </p>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            {{-- Wat er aan werk open staat --}}
            <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="text-sm font-bold text-white">Open <span class="text-brand-500">taken</span></h2>
                    <a href="{{ route('projects.show', [$project, 'tab' => 'taken']) }}" class="text-xs font-semibold text-slate-400 transition hover:text-white">Alle taken</a>
                </div>

                @forelse ($tasks as $task)
                    <div class="flex items-center gap-3 border-b border-surface-700 py-2.5 last:border-b-0">
                        <x-signal-dot :color="$task->deadline?->isPast() ? 'red' : 'gray'" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm text-slate-200">{{ $task->title }}</span>
                            <span class="block text-xs text-slate-500">
                                {{ $task->owner?->name ?? 'niet toegewezen' }}@if ($task->deadline), {{ $task->deadline->translatedFormat('j M') }} @endif
                            </span>
                        </span>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $task->status->badgeClasses() }}">{{ $task->status->label() }}</span>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Geen open taken op dit project.</p>
                @endforelse
            </section>

            {{-- Komende planning --}}
            <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="text-sm font-bold text-white">Komende <span class="text-brand-500">planning</span></h2>
                    <a href="{{ route('projects.show', [$project, 'tab' => 'planning']) }}" class="text-xs font-semibold text-slate-400 transition hover:text-white">Hele planning</a>
                </div>

                @forelse ($entries as $entry)
                    <div class="flex items-center gap-3 border-b border-surface-700 py-2.5 last:border-b-0">
                        <span class="w-16 shrink-0 text-xs font-bold text-slate-300">{{ $entry->date->translatedFormat('j M') }}</span>
                        <span class="min-w-0 flex-1 truncate text-sm text-slate-200">{{ $entry->displayTitle() }}</span>
                        <span class="shrink-0 text-xs text-slate-500">{{ $entry->user?->name }}</span>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Niets ingepland vanaf vandaag.</p>
                @endforelse
            </section>

            {{-- Laatste foto's --}}
            @if ($photos->isNotEmpty())
                <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h2 class="text-sm font-bold text-white">Laatste <span class="text-brand-500">foto's</span></h2>
                        <a href="{{ route('projects.show', [$project, 'tab' => 'fotos']) }}" class="text-xs font-semibold text-slate-400 transition hover:text-white">Alle foto's</a>
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
                <h2 class="mb-3 text-sm font-bold text-white">Project<span class="text-brand-500">info</span></h2>
                <dl class="space-y-2.5 text-sm">
                    <div>
                        <dt class="text-xs text-slate-500">Klant</dt>
                        <dd><a href="{{ route('customers.show', $project->customer) }}" class="font-semibold text-klant-300 transition hover:text-klant-200">{{ $project->customer->name }}</a></dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Adres</dt>
                        <dd class="text-slate-200">{{ collect([$project->address, $project->city])->filter()->join(', ') ?: 'onbekend' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Looptijd</dt>
                        <dd class="text-slate-200">
                            {{ $project->start_date?->translatedFormat('j M Y') ?? 'geen start' }}
                            tot {{ $project->end_date_actual?->translatedFormat('j M Y') ?? $project->end_date_expected?->translatedFormat('j M Y') ?? 'onbekend' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Uitvoerders</dt>
                        <dd class="text-slate-200">{{ $project->craftsmen->pluck('name')->join(', ') ?: 'nog niemand toegewezen' }}</dd>
                    </div>
                    @if ($project->end_date_actual)
                        <div>
                            <dt class="text-xs text-slate-500">Garantie</dt>
                            <dd class="text-slate-200">
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
                        <label for="status" class="mb-1.5 block text-xs text-slate-500">Status</label>
                        <select name="status" id="status" onchange="this.form.submit()"
                                class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
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
                    <h2 class="mb-2 text-sm font-bold text-white">Oplever<span class="text-brand-500">rapport</span></h2>

                    @if ($deliveryReport)
                        <p class="mb-3 text-xs text-slate-400">
                            Opgemaakt {{ $deliveryReport->generated_at->translatedFormat('j M Y') }}.
                            {{ $deliveryReport->client_signed_at ? 'Getekend door de klant.' : 'Nog niet getekend door de klant.' }}
                        </p>
                        <a href="{{ route('delivery-reports.show', $deliveryReport) }}"
                           class="block w-full rounded-xl border border-surface-600 py-2.5 text-center text-sm font-semibold text-slate-200 transition hover:text-white">
                            Rapport openen
                        </a>
                    @else
                        <p class="mb-3 text-xs text-slate-400">Nog geen rapport. Dat bevriest de scope, het bewijs en de restpunten.</p>
                        <form method="POST" action="{{ route('delivery-reports.store', $project) }}">
                            @csrf
                            <button type="submit" class="w-full rounded-xl bg-brand-500 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600">
                                Concept opmaken
                            </button>
                        </form>
                    @endif
                </section>
            @endcan

            {{-- Laatste activiteiten --}}
            <section class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="text-sm font-bold text-white">Laatste <span class="text-brand-500">activiteit</span></h2>
                    <a href="{{ route('projects.show', [$project, 'tab' => 'activiteiten']) }}" class="text-xs font-semibold text-slate-400 transition hover:text-white">Alles</a>
                </div>

                @forelse ($events as $event)
                    <div class="border-b border-surface-700 py-2 last:border-b-0">
                        <p class="text-sm text-slate-200">{{ $event->title }}</p>
                        <p class="text-xs text-slate-500">{{ $event->happened_at->translatedFormat('j M H:i') }}, {{ $event->source->label() }}</p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Nog niets vastgelegd.</p>
                @endforelse
            </section>
        </div>
    </div>
</div>
