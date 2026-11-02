<x-layouts.app :title="'Klantportaal, '.$project->name">
    <div class="mb-4">
        <a href="{{ route('portal-preview.index') }}" class="flex items-center gap-1 text-sm font-semibold text-slate-400 transition hover:text-white">
            <x-icon name="chevron-left" class="h-4 w-4" /> Klantportaal
        </a>
    </div>

    <x-page-header :title="$project->name" eyebrow="Zo ziet de klant het" category="klant"
                   :subtitle="$project->customer?->name">
        <a href="{{ route('projects.show', $project) }}"
           class="rounded-full border border-surface-600 px-4 py-2.5 text-sm font-semibold text-slate-200 transition hover:text-white">
            Naar het dossier
        </a>
    </x-page-header>

    @unless ($hasAccount)
        <div class="mb-4 flex items-start gap-3 rounded-2xl border border-amber-500/25 bg-amber-500/10 p-4">
            <x-icon name="key" class="h-5 w-5 shrink-0 text-amber-300" />
            <p class="text-sm text-slate-200">
                <b class="text-white">Deze klant heeft nog geen portaalaccount.</b>
                Dit is wat hij zou zien. Een account maak je aan op de klantpagina.
            </p>
        </div>
    @endunless

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            {{-- Wat de klant bovenaan ziet --}}
            <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-4 text-sm font-bold text-white">Voortgang <span class="text-brand-500">zoals de klant die ziet</span></h2>

                <div class="mb-4 flex items-center gap-4">
                    <span class="h-3 flex-1 overflow-hidden rounded-full bg-surface-700">
                        <span class="block h-full rounded-full bg-klant-500" style="width: {{ $project->progress }}%"></span>
                    </span>
                    <span class="text-xl font-bold text-white">{{ $project->progress }}%</span>
                </div>

                <p class="text-sm {{ $project->end_date_expected?->isPast() && $project->end_date_actual === null ? 'text-amber-300' : 'text-slate-300' }}">
                    @if ($project->end_date_actual)
                        Opgeleverd op {{ $project->end_date_actual->translatedFormat('j F Y') }}.
                    @elseif ($project->end_date_expected?->isPast())
                        De verwachte opleverdatum van {{ $project->end_date_expected->translatedFormat('j F') }} is verstreken.
                        Dat ziet de klant ook.
                    @elseif ($project->end_date_expected)
                        Verwachte oplevering {{ $project->end_date_expected->translatedFormat('j F Y') }}, op schema.
                    @else
                        Nog geen verwachte opleverdatum vastgelegd.
                    @endif
                </p>
            </div>

            {{-- Actie van de klant nodig --}}
            @if ($waiting->isNotEmpty())
                <div class="rounded-2xl border border-brand-500/25 bg-brand-500/5 p-5">
                    <h2 class="mb-1 text-sm font-bold text-white">Actie van <span class="text-brand-500">de klant nodig</span></h2>
                    <p class="mb-4 text-xs text-slate-400">Dit staat bij de klant bovenaan op het scherm.</p>

                    <div class="space-y-2">
                        @foreach ($waiting as $phase)
                            <div class="flex items-center gap-3 rounded-xl border border-surface-700 bg-surface-800 p-3">
                                <x-icon name="clock" class="h-4 w-4 shrink-0 text-brand-400" />
                                <span class="min-w-0 flex-1 text-sm text-slate-200">{{ $phase->name }}</span>
                                <span class="shrink-0 text-xs text-slate-500">wacht op de klant</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Foto's die de klant ziet --}}
            <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-1 text-sm font-bold text-white">Foto's <span class="text-brand-500">die de klant ziet</span></h2>
                <p class="mb-4 text-xs text-slate-500">
                    {{ $project->photos->count() }} zichtbaar, {{ $hidden }} alleen intern.
                    Zichtbaarheid zet je om op het tabblad Foto's in het dossier.
                </p>

                @if ($project->photos->isEmpty())
                    <p class="text-sm text-slate-500">De klant ziet nog geen foto's.</p>
                @else
                    <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                        @foreach ($project->photos->take(12) as $foto)
                            <img src="{{ route('photos.show', $foto) }}" alt="{{ $foto->caption }}"
                                 class="aspect-square w-full rounded-lg object-cover" title="{{ $foto->caption }}">
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="space-y-4">
            {{-- Wat de klant al heeft afgetekend --}}
            <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-3 text-sm font-bold text-white">Afgetekend</h2>

                @if ($approved->isEmpty())
                    <p class="text-sm text-slate-500">De klant heeft nog niets voor gezien en akkoord gezet.</p>
                @else
                    <div class="space-y-2">
                        @foreach ($approved as $phase)
                            <div class="flex items-start gap-2.5">
                                <x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-green-400" />
                                <span class="min-w-0">
                                    <span class="block text-sm text-slate-200">{{ $phase->name }}</span>
                                    <span class="block text-xs text-slate-500">
                                        {{ $phase->client_approved_by }}, {{ $phase->client_approved_at->translatedFormat('j M Y') }}
                                    </span>
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Wat er komende week gepland staat --}}
            <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-3 text-sm font-bold text-white">Deze <span class="text-brand-500">week</span></h2>

                @php($week = $project->scheduleEntries->filter(fn ($e) => $e->date->betweenIncluded(today(), today()->addDays(7))))

                @if ($week->isEmpty())
                    <p class="text-sm text-slate-500">Niets ingepland deze week.</p>
                @else
                    <div class="space-y-2">
                        @foreach ($week->sortBy('date') as $entry)
                            <div class="text-sm">
                                <span class="block text-slate-200">{{ $entry->displayTitle() }}</span>
                                <span class="block text-xs text-slate-500">
                                    {{ $entry->date->translatedFormat('l j M') }}, {{ $entry->user?->name }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            @if ($project->serviceTickets->isNotEmpty())
                <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                    <h2 class="mb-3 text-sm font-bold text-white">Service</h2>
                    <div class="space-y-2">
                        @foreach ($project->serviceTickets->take(5) as $ticket)
                            <div class="flex items-center gap-2 text-sm">
                                <span class="min-w-0 flex-1 truncate text-slate-200">{{ $ticket->title }}</span>
                                <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $ticket->badgeClasses() }}">{{ $ticket->statusLabel() }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
