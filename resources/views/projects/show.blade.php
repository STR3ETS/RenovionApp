<x-layouts.app :title="$project->name">

    <x-page-header eyebrow="Project" category="project" :title="$project->name" :subtitle="$project->customer->name.', '.($project->city ?? 'plaats onbekend')"
                   :image="($project->cover_photo_path || $project->photos->where('client_visible', true)->isNotEmpty()) ? route('projects.cover', $project) : null">
        <x-status-badge :status="$project->status" class="text-sm" />
        @if ($project->chatChannel)
            <a href="{{ route('chat.show', $project->chatChannel) }}" class="flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50"><x-icon name="chat-bubble" class="h-4 w-4" /> Chat</a>
        @endif
        @can('manage-crm')
            <a href="{{ route('projects.edit', $project) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Bewerken</a>
        @endcan
    </x-page-header>

    {{-- Fasering (briefing §7): voortgang + fase-stepper --}}
    <section class="mb-4 rounded-2xl border border-gray-200 bg-white p-5">
        <div class="mb-2 flex items-center justify-between">
            <h2 class="text-sm font-bold text-navy-900">Projectvoortgang</h2>
            <span class="text-xs font-semibold text-gray-500">{{ $project->progress }}%</span>
        </div>
        <div class="mb-5 h-2 overflow-hidden rounded-full bg-gray-100">
            <div class="h-full rounded-full bg-brand-500" style="width: {{ $project->progress }}%"></div>
        </div>
        <x-phase-stepper :project="$project" />
    </section>

    {{-- ?tab=uitvoering|fotos opent direct die tab (snelkoppelingen op het uitvoerder-dashboard) --}}
    <div x-data="{ tab: @js(in_array(request('tab'), ['uitvoering', 'fotos'], true) ? request('tab') : 'overzicht'), fotoFilter: 'alle' }">

    {{-- Tabs (mockup §18) --}}
    <div class="mb-5 flex gap-1 rounded-xl border border-gray-200 bg-white p-1 text-sm font-semibold">
        <button type="button" @click="tab = 'overzicht'" :class="tab === 'overzicht' ? 'bg-navy-950 text-white' : 'text-gray-600 hover:bg-gray-50'" class="min-w-0 flex-1 rounded-lg px-2 py-2.5 transition">Overzicht</button>
        <button type="button" @click="tab = 'uitvoering'" :class="tab === 'uitvoering' ? 'bg-navy-950 text-white' : 'text-gray-600 hover:bg-gray-50'" class="flex min-w-0 flex-1 items-center justify-center gap-1 rounded-lg px-2 py-2.5 whitespace-nowrap transition">
            Uitvoering
            @php $openPakketten = $project->phases->flatMap->workPackages->reject->isDone()->count(); @endphp
            @if ($openPakketten > 0)
                <span class="rounded-full bg-brand-500 px-1.5 py-0.5 text-[10px] leading-none font-bold text-white">{{ $openPakketten }}</span>
            @endif
        </button>
        <button type="button" @click="tab = 'fotos'" :class="tab === 'fotos' ? 'bg-navy-950 text-white' : 'text-gray-600 hover:bg-gray-50'" class="flex min-w-0 flex-1 items-center justify-center gap-1 rounded-lg px-2 py-2.5 whitespace-nowrap transition">
            Foto's
            @if ($project->photos->isNotEmpty())
                <span class="text-xs opacity-70">{{ $project->photos->count() }}</span>
            @endif
        </button>
    </div>

    {{-- ===== Tab: Foto's & voortgang (briefing §9, mockup §18) ===== --}}
    <div x-show="tab === 'fotos'" x-cloak>
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
            <div class="space-y-4">
                {{-- Filter per fase --}}
                <nav class="space-y-1 rounded-2xl border border-gray-200 bg-white p-2">
                    <button type="button" @click="fotoFilter = 'alle'"
                            :class="fotoFilter === 'alle' ? 'bg-navy-950 text-white' : 'text-gray-600 hover:bg-gray-50'"
                            class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-sm font-semibold transition">
                        Alle foto's <span class="text-xs opacity-70">{{ $project->photos->count() }}</span>
                    </button>
                    @foreach ($project->phases as $phase)
                        @php $faseFotos = $project->photos->where('project_phase_id', $phase->id)->count(); @endphp
                        @continue($faseFotos === 0)
                        <button type="button" @click="fotoFilter = '{{ $phase->id }}'"
                                :class="fotoFilter === '{{ $phase->id }}' ? 'bg-navy-950 text-white' : 'text-gray-600 hover:bg-gray-50'"
                                class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-sm font-semibold transition">
                            <span class="truncate">{{ $phase->name }}</span> <span class="text-xs opacity-70">{{ $faseFotos }}</span>
                        </button>
                    @endforeach
                </nav>

                {{-- Uploaden --}}
                <form method="POST" action="{{ route('photos.store', $project) }}" enctype="multipart/form-data" class="space-y-3 rounded-2xl border border-gray-200 bg-white p-4"
                      x-data="{ files: [] }">
                    @csrf
                    <h2 class="text-sm font-bold text-navy-900">Foto's toevoegen</h2>
                    <x-photo-button />
                    <select name="project_phase_id" aria-label="Fase" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">- Fase (optioneel) -</option>
                        @foreach ($project->phases as $phase)
                            <option value="{{ $phase->id }}" @selected($project->currentPhase()?->is($phase))>{{ $phase->name }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="caption" maxlength="255" placeholder="Omschrijving (bijv. Leidingwerk badkamer)" aria-label="Omschrijving" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <label class="flex min-h-11 items-center gap-2 text-xs font-medium text-gray-600">
                        <input type="hidden" name="client_visible" value="0">
                        <input type="checkbox" name="client_visible" value="1" checked class="rounded border-gray-300 text-brand-700 focus:ring-brand-500">
                        Zichtbaar voor de klant
                    </label>
                    <button type="submit" :disabled="!files.length" class="w-full rounded-xl bg-gray-100 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-200 disabled:opacity-40">Uploaden</button>
                </form>
            </div>

            {{-- Fotogrid --}}
            <div class="lg:col-span-3">
                @if ($project->photos->isEmpty())
                    <x-empty-state title="Nog geen foto's" subtitle="Upload foto's per fase of vanaf een werkpakket - zo bouw je het bewijs voor de oplevering op." icon="camera" category="project" />
                @else
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        @foreach ($project->photos as $photo)
                            <figure x-show="fotoFilter === 'alle' || fotoFilter === '{{ $photo->project_phase_id }}'"
                                    class="group relative overflow-hidden rounded-xl border border-gray-200 bg-white">
                                <button type="button" class="block w-full cursor-zoom-in text-left" title="Foto bekijken"
                                        data-photo="{{ route('photos.show', $photo) }}" data-caption="{{ $photo->caption ?? $photo->workPackage?->name ?? 'Projectfoto' }}"
                                        data-meta="{{ collect([$photo->created_at->translatedFormat('j M Y H:i'), $photo->uploader?->name, $photo->phase?->name])->filter()->implode(', ') }}">
                                    <img src="{{ route('photos.show', $photo) }}" alt="{{ $photo->caption ?? 'Projectfoto' }}" loading="lazy" class="h-36 w-full object-cover sm:h-40">
                                    <span class="block px-3 py-2">
                                        <span class="block truncate text-xs font-semibold text-navy-900">{{ $photo->caption ?? $photo->workPackage?->name ?? 'Projectfoto' }}</span>
                                        <span class="block truncate text-[11px] text-gray-500">{{ $photo->created_at->translatedFormat('j M') }}{{ $photo->uploader ? ', '.$photo->uploader->name : '' }}</span>
                                    </span>
                                </button>
                                @unless ($photo->client_visible)
                                    <span class="absolute top-2 left-2 rounded-full bg-navy-950/80 px-2 py-0.5 text-[10px] font-semibold text-white">Intern</span>
                                @endunless
                                @if (auth()->user()->can('manage-crm') || $photo->uploaded_by === auth()->id())
                                    <form method="POST" action="{{ route('photos.destroy', $photo) }}" onsubmit="return confirm('Foto verwijderen?');"
                                          class="absolute top-2 right-2 transition pointer-fine:opacity-0 pointer-fine:group-hover:opacity-100">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="tap flex h-7 w-7 items-center justify-center rounded-full bg-white/90 text-gray-500 shadow hover:text-red-600" title="Verwijderen">
                                            <x-icon name="trash" class="h-3.5 w-3.5" />
                                        </button>
                                    </form>
                                @endif
                            </figure>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ===== Tab: Uitvoering - fasen, gates en werkpakketten (briefing §7) ===== --}}
    <div x-show="tab === 'uitvoering'" x-cloak class="space-y-3">
        @foreach ($project->phases as $phase)
            @php
                $isActief = $project->currentPhase()?->is($phase);
                // Afgeronde fasen zijn op mobiel ingeklapt (één regel), zodat je snel bij de huidige fase bent.
                $isInklapbaar = $phase->status === \App\Enums\PhaseStatus::Gereed && $phase->workPackages->isNotEmpty();
            @endphp
            <section class="rounded-2xl border bg-white {{ $isActief ? 'border-brand-300 ring-1 ring-brand-100' : 'border-gray-200' }}"
                     x-data="{ open: {{ $isInklapbaar ? "window.matchMedia('(min-width: 1024px)').matches" : 'true' }} }">
                <header class="flex flex-wrap items-center gap-3 px-5 py-4">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[11px] font-bold {{ $phase->status === \App\Enums\PhaseStatus::Gereed ? 'bg-green-500 text-white' : ($isActief ? 'bg-brand-500 text-white' : 'bg-gray-200 text-gray-500') }}">
                        @if ($phase->status === \App\Enums\PhaseStatus::Gereed)<x-icon name="check" class="h-3.5 w-3.5" />@else{{ $phase->position }}@endif
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold text-navy-900">{{ $phase->name }}</span>
                        <span class="block text-xs text-gray-500">
                            {{ collect([
                                $phase->responsible?->name ?? 'Geen verantwoordelijke',
                                $phase->approved_at ? 'vrijgegeven door '.($phase->approver?->name ?? '-').' op '.$phase->approved_at->translatedFormat('j M') : null,
                                $phase->client_approved_at ? 'klant: gezien en akkoord ('.$phase->client_approved_at->translatedFormat('j M').')' : null,
                            ])->filter()->implode(', ') }}
                        </span>
                    </span>

                    @if ($isInklapbaar)
                        <button type="button" @click="open = !open" :aria-expanded="open"
                                class="tap flex items-center gap-1 rounded-lg px-2 py-1.5 text-xs font-semibold text-gray-500 transition hover:bg-gray-50 active:bg-gray-100">
                            {{ $phase->workPackages->count() }} {{ $phase->workPackages->count() === 1 ? 'werkpakket' : 'werkpakketten' }}
                            <x-icon name="chevron-down" class="h-3.5 w-3.5 transition" ::class="open && 'rotate-180'" />
                        </button>
                    @endif

                    @can('manage-crm')
                        {{-- Op mobiel een eigen regel onder de fasenaam (uitgelijnd met de naam), anders drukt
                             de keuzelijst de naam weg; vanaf sm naast de naam. --}}
                        <div class="flex w-full items-center gap-2 pl-10 sm:w-auto sm:pl-0">
                        <form method="POST" action="{{ route('phases.update', $phase) }}" class="min-w-0 flex-1 sm:flex-none">
                            @csrf @method('PATCH')
                            <select name="status" onchange="this.form.submit()" aria-label="Status van {{ $phase->name }}" class="w-full rounded-lg border-gray-200 py-1.5 text-xs font-semibold focus:border-brand-500 focus:ring-brand-500 sm:w-auto pointer-coarse:py-2.5">
                                @foreach (\App\Enums\PhaseStatus::cases() as $statusOption)
                                    <option value="{{ $statusOption->value }}" @selected($phase->status === $statusOption)>{{ $statusOption->label() }}</option>
                                @endforeach
                            </select>
                        </form>
                        @if ($phase->status !== \App\Enums\PhaseStatus::Gereed)
                            <details class="relative shrink-0">
                                <summary class="tap cursor-pointer list-none rounded-lg bg-navy-950 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-navy-900 [&::-webkit-details-marker]:hidden">Vrijgeven</summary>
                                <form method="POST" action="{{ route('phases.approve', $phase) }}" class="absolute right-0 z-20 mt-2 w-64 space-y-2 rounded-xl border border-gray-200 bg-white p-3 shadow-lg">
                                    @csrf
                                    <p class="text-xs text-gray-500">Gate: alle werkpakketten in deze fase moeten gereed zijn.</p>
                                    <input type="text" name="gate_note" maxlength="255" placeholder="Bewijs/notitie (optioneel)" aria-label="Bewijs of notitie" class="w-full rounded-lg border-gray-300 text-xs focus:border-brand-500 focus:ring-brand-500">
                                    <button type="submit" class="w-full rounded-lg bg-brand-500 py-2 text-xs font-bold text-white transition hover:bg-brand-600 active:bg-brand-600">Fase vrijgeven</button>
                                </form>
                            </details>
                        @endif
                        </div>
                    @else
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $phase->status->badgeClasses() }}">{{ $phase->status->label() }}</span>
                    @endcan
                </header>

                @if ($phase->workPackages->isNotEmpty() || auth()->user()->can('manage-crm'))
                    <div class="border-t border-gray-100 px-5 py-3" x-show="open" @if ($isInklapbaar) x-cloak @endif>
                        <div class="space-y-1.5">
                            @foreach ($phase->workPackages as $package)
                                <a href="{{ route('work-packages.show', $package) }}" class="flex items-center gap-3 rounded-xl border border-gray-200 p-2.5 transition hover:border-brand-400">
                                    <x-signal-dot :color="$package->isOverdue() ? 'red' : $package->status->dotColor()" />
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-semibold text-navy-900 {{ $package->isDone() ? 'text-gray-500 line-through' : '' }}">{{ $package->name }}</span>
                                        <span class="block text-xs text-gray-500">
                                            {{ collect([
                                                $package->responsible?->name ?? 'Niet toegewezen',
                                                $package->deadline?->translatedFormat('j M'),
                                                $package->items->isNotEmpty() ? 'checklist '.$package->items->filter->isDone()->count().'/'.$package->items->count() : null,
                                            ])->filter()->implode(', ') }}
                                        </span>
                                    </span>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold whitespace-nowrap {{ $package->status->badgeClasses() }}">{{ $package->status->label() }}</span>
                                </a>
                            @endforeach
                        </div>

                        @can('manage-crm')
                            {{-- Nieuw werkpakket: ingeklapt een knop, open één afgebakend blok met alle velden bij elkaar. --}}
                            <div x-data="{ adding: false }" class="mt-2">
                                <button type="button" x-show="!adding" @click="adding = true; $nextTick(() => $refs.naam.focus())"
                                        class="flex w-full items-center justify-center gap-1.5 rounded-xl border border-dashed border-gray-300 py-2.5 text-sm font-semibold text-gray-500 transition hover:border-brand-400 hover:text-brand-600 active:bg-gray-50">
                                    <x-icon name="plus" class="h-4 w-4" /> Werkpakket toevoegen
                                </button>

                                <form method="POST" action="{{ route('work-packages.store', $project) }}" x-show="adding" x-cloak
                                      class="space-y-3 rounded-xl border border-gray-200 bg-gray-50 p-3">
                                    @csrf
                                    <input type="hidden" name="project_phase_id" value="{{ $phase->id }}">
                                    <p class="text-xs font-bold tracking-wide text-gray-600 uppercase">Nieuw werkpakket in {{ $phase->name }}</p>

                                    <label class="block">
                                        <span class="mb-1 block text-xs font-semibold text-gray-600">Naam</span>
                                        <input x-ref="naam" type="text" name="name" required placeholder="Bijv. Elektra begane grond"
                                               class="w-full rounded-xl border-gray-200 bg-white text-sm placeholder:text-gray-400 focus:border-brand-500 focus:ring-brand-500">
                                    </label>

                                    <div class="grid grid-cols-2 gap-2">
                                        <label class="block min-w-0">
                                            <span class="mb-1 block text-xs font-semibold text-gray-600">Verantwoordelijke</span>
                                            <select name="responsible_id" class="w-full rounded-xl border-gray-200 bg-white text-sm focus:border-brand-500 focus:ring-brand-500">
                                                <option value="">Nog niemand</option>
                                                @foreach ($project->craftsmen as $member)
                                                    <option value="{{ $member->id }}">{{ $member->name }}</option>
                                                @endforeach
                                                @if ($project->projectLeader && ! $project->craftsmen->contains('id', $project->project_leader_id))
                                                    <option value="{{ $project->project_leader_id }}">{{ $project->projectLeader->name }}</option>
                                                @endif
                                            </select>
                                        </label>
                                        <label class="block min-w-0">
                                            <span class="mb-1 block text-xs font-semibold text-gray-600">Deadline</span>
                                            <input type="date" name="deadline" class="w-full rounded-xl border-gray-200 bg-white text-sm focus:border-brand-500 focus:ring-brand-500">
                                        </label>
                                    </div>

                                    <div class="flex gap-2">
                                        <button type="submit" class="flex-1 rounded-xl bg-brand-500 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600 active:bg-brand-600">Toevoegen</button>
                                        <button type="button" @click="adding = false" class="rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Annuleren</button>
                                    </div>
                                </form>
                            </div>
                        @endcan
                    </div>
                @endif
            </section>
        @endforeach
    </div>

    {{-- ===== Tab: Overzicht ===== --}}
    <div x-show="tab === 'overzicht'" class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">

            {{-- Signalen --}}
            @php
                $signalen = collect([
                    $project->isOverdue() ? ['color' => 'red', 'text' => 'Verwachte einddatum ('.$project->end_date_expected->translatedFormat('j M').') is verstreken.'] : null,
                    $project->depositOutstanding() ? ['color' => 'amber', 'text' => 'Aanbetaling van € '.number_format((float) $project->deposit_amount, 0, ',', '.').' nog niet ontvangen'.($project->start_date ? ' - start '.$project->start_date->translatedFormat('j M') : '').'.'] : null,
                    $project->next_payment_due_at?->isPast() ? ['color' => 'amber', 'text' => 'Betaaltermijn verstreken ('.$project->next_payment_due_at->translatedFormat('j M').').'] : null,
                ])->filter();
            @endphp
            @if ($signalen->isNotEmpty())
                <div class="space-y-1 rounded-xl border border-amber-300 bg-amber-50 p-4">
                    @foreach ($signalen as $signaal)
                        <p class="flex items-center gap-2 text-sm font-medium text-amber-800"><x-signal-dot :color="$signaal['color']" /> {{ $signaal['text'] }}</p>
                    @endforeach
                </div>
            @endif

            {{-- Status: alleen voor wie hem kan wijzigen. Voortgang en huidige status staan al bovenaan. --}}
            @can('manage-crm')
            <section class="rounded-2xl border border-gray-200 bg-white p-5">
                <h2 class="mb-3 text-sm font-bold text-navy-900">Status</h2>
                <form method="POST" action="{{ route('projects.status', $project) }}" class="flex flex-wrap items-center gap-2">
                    @csrf
                    @method('PATCH')
                    @foreach (\App\Enums\ProjectStatus::cases() as $statusOption)
                        <button type="submit" name="status" value="{{ $statusOption->value }}"
                                @disabled($statusOption === $project->status)
                                @if ($statusOption === \App\Enums\ProjectStatus::Afgerond && $project->status !== \App\Enums\ProjectStatus::Afgerond)
                                    onclick="return confirm('Project markeren als afgerond?');"
                                @endif
                                class="tap rounded-full px-3 py-1.5 text-xs font-semibold transition {{ $statusOption === $project->status ? 'cursor-default '.$statusOption->badgeClasses().' ring-2 ring-navy-900/20' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                            {{ $statusOption->label() }}
                        </button>
                    @endforeach
                </form>
            </section>
            @endcan

            {{-- Betalingen --}}
            @can('manage-crm')
            <section class="rounded-2xl border border-gray-200 bg-white p-5">
                <h2 class="mb-3 text-sm font-bold text-navy-900">Betalingen</h2>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                    <div><dt class="text-gray-500">Projectwaarde</dt><dd class="text-base font-bold text-navy-900">€ {{ number_format((float) $project->value, 2, ',', '.') }}</dd></div>
                    <div><dt class="text-gray-500">Openstaand</dt><dd class="text-base font-bold {{ $project->outstandingAmount() > 0 ? 'text-brand-700' : 'text-green-700' }}">€ {{ number_format($project->outstandingAmount(), 2, ',', '.') }}</dd></div>
                    <div>
                        <dt class="text-gray-500">Aanbetaling</dt>
                        <dd class="font-medium">
                            {{ $project->deposit_amount ? '€ '.number_format((float) $project->deposit_amount, 2, ',', '.') : '-' }}
                            @if ($project->deposit_amount)
                                @if ($project->deposit_received_at)
                                    <span class="ml-1 inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700"><x-icon name="check" class="h-3 w-3" /> ontvangen</span>
                                @else
                                    <span class="ml-1 inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700"><x-icon name="clock" class="h-3 w-3" /> open</span>
                                @endif
                            @endif
                        </dd>
                    </div>
                    <div><dt class="text-gray-500">Betaald</dt><dd class="font-medium">€ {{ number_format((float) $project->paid_amount, 2, ',', '.') }}</dd></div>
                    <div><dt class="text-gray-500">Volgende betaaltermijn</dt><dd class="font-medium">{{ $project->next_payment_due_at?->translatedFormat('j M Y') ?? '-' }}</dd></div>
                </dl>
            </section>
            @endcan

            {{-- Taken --}}
            <section class="rounded-2xl border border-gray-200 bg-white p-5">
                <h2 class="mb-3 text-sm font-bold text-navy-900">Open taken</h2>
                @forelse ($project->tasks as $task)
                    <div class="mb-2 flex items-center gap-3 rounded-lg border border-gray-200 p-2.5 text-sm">
                        <button
                            x-data
                            @click="patchJson('{{ route('tasks.status', $task) }}', { status: 'afgerond' }).then(() => window.location.reload())"
                            class="group -m-2.5 flex h-11 w-11 shrink-0 items-center justify-center rounded-full"
                            title="Afronden"><span class="h-5 w-5 rounded-full border-2 border-gray-300 transition group-hover:border-green-500 group-hover:bg-green-50 group-active:border-green-500 group-active:bg-green-50"></span></button>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium text-navy-900">{{ $task->title }}</span>
                            <span class="text-xs text-gray-500">{{ $task->deadline?->translatedFormat('j M') ?? 'geen deadline' }}, {{ $task->owner?->name ?? '-' }}</span>
                        </span>
                        <span class="h-2 w-2 shrink-0 rounded-full {{ $task->priority->dotClasses() }}"></span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Geen open taken.</p>
                @endforelse
            </section>

            {{-- Documenten & foto's --}}
            <section id="documenten" class="scroll-mt-24 rounded-2xl border border-gray-200 bg-white p-5">
                <h2 class="mb-3 text-sm font-bold text-navy-900">Documenten & foto's</h2>
                @forelse ($project->documents as $document)
                    <a href="{{ route('documents.show', $document) }}" class="mb-2 flex items-center gap-2 rounded-lg border border-gray-200 p-2.5 text-sm transition hover:border-brand-400">
                        <x-icon :name="$document->isImage() ? 'camera' : 'paper-clip'" class="text-gray-500" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium text-navy-900">{{ $document->name }}</span>
                            <span class="text-xs text-gray-500">{{ $document->category->label() }}, {{ $document->created_at->translatedFormat('j M Y') }}, {{ $document->uploader?->name ?? '-' }}</span>
                        </span>
                    </a>
                @empty
                    <p class="mb-3 text-sm text-gray-500">Nog geen documenten.</p>
                @endforelse

                <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-3">
                    @csrf
                    <input type="hidden" name="documentable_type" value="project">
                    <input type="hidden" name="documentable_id" value="{{ $project->id }}">
                    <input type="file" name="file" required class="text-xs text-gray-500 file:mr-2 file:rounded-lg file:border-0 file:bg-navy-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-navy-700 sm:col-span-1">
                    <select name="category" aria-label="Soort document" class="rounded-lg border-gray-300 text-xs focus:border-brand-500 focus:ring-brand-500">
                        @foreach (\App\Enums\DocumentCategory::cases() as $category)
                            <option value="{{ $category->value }}" @selected($category === \App\Enums\DocumentCategory::Foto)>{{ $category->label() }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-lg bg-gray-100 py-2 text-xs font-semibold text-gray-700 transition hover:bg-gray-200">Uploaden</button>
                </form>
            </section>
        </div>

        <div class="space-y-4">
            {{-- Kerninfo --}}
            <section class="rounded-2xl border border-gray-200 bg-white p-5">
                <h2 class="mb-3 text-sm font-bold text-navy-900">Projectinfo</h2>
                <dl class="space-y-2 text-sm">
                    <div><dt class="text-gray-500">Klant</dt><dd class="font-medium">
                        @can('manage-crm')
                            <a href="{{ route('customers.show', $project->customer) }}" class="font-medium text-klant-700 hover:text-klant-500">{{ $project->customer->name }}</a>
                        @else
                            {{ $project->customer->name }}
                        @endcan
                    </dd></div>
                    <div><dt class="text-gray-500">Adres</dt><dd class="font-medium">{{ $project->address ?? '-' }}, {{ $project->city ?? '' }}</dd></div>
                    <div><dt class="text-gray-500">Startdatum</dt><dd class="font-medium">{{ $project->start_date?->translatedFormat('j M Y') ?? '-' }}</dd></div>
                    <div><dt class="text-gray-500">Verwachte oplevering</dt><dd class="font-medium {{ $project->isOverdue() ? 'text-red-600' : '' }}">{{ $project->end_date_expected?->translatedFormat('j M Y') ?? '-' }}</dd></div>
                    @if ($project->end_date_actual)
                        <div><dt class="text-gray-500">Opgeleverd</dt><dd class="font-medium text-green-700">{{ $project->end_date_actual->translatedFormat('j M Y') }}</dd></div>
                    @endif
                    <div><dt class="text-gray-500">Projectleider</dt><dd class="font-medium">{{ $project->projectLeader?->name ?? 'Niet toegewezen' }}</dd></div>
                    @can('manage-crm')
                        @if ($project->quote && config('renovion.modules.quotes'))
                            <div><dt class="text-gray-500">Offerte</dt><dd class="font-medium"><a href="{{ route('quotes.show', $project->quote) }}" class="text-offerte-700 hover:text-offerte-500">{{ $project->quote->number }}</a></dd></div>
                        @endif
                    @endcan
                </dl>
            </section>

            {{-- Opleverrapport (briefing §13) --}}
            <section class="rounded-2xl border border-gray-200 bg-white p-5">
                <h2 class="mb-3 text-sm font-bold text-navy-900">Opleverrapport</h2>
                @if ($project->deliveryReport)
                    <p class="text-xs text-gray-500">
                        Opgesteld {{ $project->deliveryReport->generated_at->translatedFormat('j M Y') }},
                        @if ($project->deliveryReport->isFullySigned())
                            <span class="font-semibold text-green-700">volledig ondertekend</span>
                        @elseif ($project->deliveryReport->isSignedByClient())
                            <span class="font-semibold text-green-700">klant heeft getekend</span>
                        @else
                            wacht op ondertekening
                        @endif
                    </p>
                    <a href="{{ route('delivery-reports.show', $project->deliveryReport) }}" class="mt-3 block rounded-xl bg-navy-950 py-2.5 text-center text-sm font-semibold text-white transition hover:bg-navy-900">Rapport bekijken</a>
                @else
                    <p class="text-xs text-gray-500">Genereert automatisch een rapport uit fasen, foto-bewijs, wijzigingen en restpunten.</p>
                @endif
                @can('manage-crm')
                    @unless ($project->deliveryReport?->isSignedByClient())
                        <form method="POST" action="{{ route('delivery-reports.store', $project) }}" class="mt-2">
                            @csrf
                            <button type="submit" class="w-full rounded-xl {{ $project->deliveryReport ? 'border border-gray-300 bg-white text-gray-600 hover:bg-gray-50' : 'bg-brand-500 text-white hover:bg-brand-600 font-bold' }} py-2.5 text-sm font-semibold transition">
                                {{ $project->deliveryReport ? 'Hergenereren met actuele data' : 'Opleverrapport genereren' }}
                            </button>
                        </form>
                    @endunless
                @endcan
            </section>

            {{-- Uitvoerders --}}
            <section class="rounded-2xl border border-gray-200 bg-white p-5">
                <h2 class="mb-3 text-sm font-bold text-navy-900">Uitvoerders</h2>
                @forelse ($project->craftsmen as $craftsman)
                    <div class="mb-2 flex items-center gap-2 text-sm">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-xs font-bold text-brand-700">{{ str($craftsman->name)->substr(0, 1)->upper() }}</span>
                        <span class="font-medium text-navy-900">{{ $craftsman->name }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Nog niemand ingepland.</p>
                @endforelse
            </section>

            {{-- Komende planning --}}
            <section class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-navy-900">Komende planning</h2>
                    <a href="{{ route('planning.index') }}" class="tap text-xs font-semibold text-planning-700 hover:text-planning-500">Planning →</a>
                </div>
                @forelse ($project->scheduleEntries as $entry)
                    <div class="mb-2 rounded-lg border p-2.5 text-sm {{ $entry->type->blockClasses() }}">
                        <p class="font-semibold">{{ $entry->date->translatedFormat('D j M') }}</p>
                        <p class="text-xs opacity-70">{{ $entry->user->name }}</p>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Niets gepland.</p>
                @endforelse
            </section>

            @if ($project->notes)
                <section class="rounded-2xl border border-gray-200 bg-white p-5">
                    <h2 class="mb-2 text-sm font-bold text-navy-900">Notities</h2>
                    <p class="text-sm whitespace-pre-line text-gray-600">{{ $project->notes }}</p>
                </section>
            @endif
        </div>
    </div>

    </div>

</x-layouts.app>
