<x-layouts.app :title="$project->name">

    <x-page-header :title="$project->name" :subtitle="$project->customer->name.' · '.($project->city ?? 'plaats onbekend')"
                   :image="$project->cover_photo_path ? route('projects.cover', $project) : null">
        <x-status-badge :status="$project->status" class="text-sm" />
        @can('manage-crm')
            <a href="{{ route('projects.edit', $project) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Bewerken</a>
        @endcan
    </x-page-header>

    {{-- Fasering (briefing §7): voortgang + fase-stepper --}}
    <section class="mb-4 rounded-2xl border border-gray-200 bg-white p-5">
        <div class="mb-2 flex items-center justify-between">
            <h2 class="text-sm font-bold text-navy-900">Projectvoortgang</h2>
            <span class="text-xs font-semibold text-gray-400">{{ $project->progress }}%</span>
        </div>
        <div class="mb-5 h-2 overflow-hidden rounded-full bg-gray-100">
            <div class="h-full rounded-full bg-brand-500" style="width: {{ $project->progress }}%"></div>
        </div>
        <x-phase-stepper :project="$project" />
    </section>

    <div x-data="{ tab: 'overzicht' }">

    {{-- Tabs (mockup §18) --}}
    <div class="mb-5 flex gap-1 rounded-xl border border-gray-200 bg-white p-1 text-sm font-semibold">
        <button type="button" @click="tab = 'overzicht'" :class="tab === 'overzicht' ? 'bg-navy-950 text-white' : 'text-gray-600 hover:bg-gray-50'" class="flex-1 rounded-lg px-4 py-2 transition">Overzicht</button>
        <button type="button" @click="tab = 'uitvoering'" :class="tab === 'uitvoering' ? 'bg-navy-950 text-white' : 'text-gray-600 hover:bg-gray-50'" class="flex-1 rounded-lg px-4 py-2 transition">
            Uitvoering
            @php $openPakketten = $project->phases->flatMap->workPackages->reject->isDone()->count(); @endphp
            @if ($openPakketten > 0)
                <span class="ml-1 rounded-full bg-brand-500 px-1.5 py-0.5 text-[10px] font-bold text-white">{{ $openPakketten }}</span>
            @endif
        </button>
    </div>

    {{-- ===== Tab: Uitvoering — fasen, gates en werkpakketten (briefing §7) ===== --}}
    <div x-show="tab === 'uitvoering'" x-cloak class="space-y-3">
        @foreach ($project->phases as $phase)
            @php $isActief = $project->currentPhase()?->is($phase); @endphp
            <section class="rounded-2xl border bg-white {{ $isActief ? 'border-brand-300 ring-1 ring-brand-100' : 'border-gray-200' }}">
                <header class="flex flex-wrap items-center gap-3 px-5 py-4">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[11px] font-bold {{ $phase->status === \App\Enums\PhaseStatus::Gereed ? 'bg-green-500 text-white' : ($isActief ? 'bg-brand-500 text-white' : 'bg-gray-200 text-gray-500') }}">
                        @if ($phase->status === \App\Enums\PhaseStatus::Gereed)<x-icon name="check" class="h-3.5 w-3.5" />@else{{ $phase->position }}@endif
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold text-navy-900">{{ $phase->name }}</span>
                        <span class="block text-xs text-gray-400">
                            {{ $phase->responsible?->name ?? 'Geen verantwoordelijke' }}
                            @if ($phase->approved_at) · vrijgegeven door {{ $phase->approver?->name ?? '—' }} op {{ $phase->approved_at->translatedFormat('j M') }} @endif
                        </span>
                    </span>

                    @can('manage-crm')
                        <form method="POST" action="{{ route('phases.update', $phase) }}">
                            @csrf @method('PATCH')
                            <select name="status" onchange="this.form.submit()" class="rounded-lg border-gray-200 py-1.5 text-xs font-semibold focus:border-brand-500 focus:ring-brand-500">
                                @foreach (\App\Enums\PhaseStatus::cases() as $statusOption)
                                    <option value="{{ $statusOption->value }}" @selected($phase->status === $statusOption)>{{ $statusOption->label() }}</option>
                                @endforeach
                            </select>
                        </form>
                        @if ($phase->status !== \App\Enums\PhaseStatus::Gereed)
                            <details class="relative">
                                <summary class="cursor-pointer rounded-lg bg-navy-950 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-navy-900">Vrijgeven</summary>
                                <form method="POST" action="{{ route('phases.approve', $phase) }}" class="absolute right-0 z-20 mt-2 w-64 space-y-2 rounded-xl border border-gray-200 bg-white p-3 shadow-lg">
                                    @csrf
                                    <p class="text-xs text-gray-500">Gate: alle werkpakketten in deze fase moeten gereed zijn.</p>
                                    <input type="text" name="gate_note" maxlength="255" placeholder="Bewijs/notitie (optioneel)" class="w-full rounded-lg border-gray-300 text-xs focus:border-brand-500 focus:ring-brand-500">
                                    <button type="submit" class="w-full rounded-lg bg-brand-500 py-2 text-xs font-bold text-white transition hover:bg-brand-600">Fase vrijgeven</button>
                                </form>
                            </details>
                        @endif
                    @else
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $phase->status->badgeClasses() }}">{{ $phase->status->label() }}</span>
                    @endcan
                </header>

                @if ($phase->workPackages->isNotEmpty() || auth()->user()->can('manage-crm'))
                    <div class="border-t border-gray-100 px-5 py-3">
                        <div class="space-y-1.5">
                            @foreach ($phase->workPackages as $package)
                                <a href="{{ route('work-packages.show', $package) }}" class="flex items-center gap-3 rounded-xl border border-gray-200 p-2.5 transition hover:border-brand-400">
                                    <x-signal-dot :color="$package->isOverdue() ? 'red' : $package->status->dotColor()" />
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-semibold text-navy-900 {{ $package->isDone() ? 'text-gray-400 line-through' : '' }}">{{ $package->name }}</span>
                                        <span class="block text-xs text-gray-400">
                                            {{ $package->responsible?->name ?? 'Niet toegewezen' }}
                                            @if ($package->deadline) · {{ $package->deadline->translatedFormat('j M') }} @endif
                                            @if ($package->items->isNotEmpty()) · checklist {{ $package->items->filter->isDone()->count() }}/{{ $package->items->count() }} @endif
                                        </span>
                                    </span>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold whitespace-nowrap {{ $package->status->badgeClasses() }}">{{ $package->status->label() }}</span>
                                </a>
                            @endforeach
                        </div>

                        @can('manage-crm')
                            <form method="POST" action="{{ route('work-packages.store', $project) }}" class="mt-2 flex flex-wrap gap-2">
                                @csrf
                                <input type="hidden" name="project_phase_id" value="{{ $phase->id }}">
                                <input type="text" name="name" required placeholder="+ Werkpakket (bijv. Elektra begane grond)" class="min-w-48 flex-1 rounded-xl border-gray-200 bg-gray-50 text-sm placeholder:text-gray-400 focus:border-brand-500 focus:bg-white focus:ring-brand-500">
                                <select name="responsible_id" class="rounded-xl border-gray-200 bg-gray-50 text-sm focus:border-brand-500 focus:ring-brand-500">
                                    <option value="">Verantwoordelijke</option>
                                    @foreach ($project->craftsmen as $member)
                                        <option value="{{ $member->id }}">{{ $member->name }}</option>
                                    @endforeach
                                    @if ($project->projectLeader && ! $project->craftsmen->contains('id', $project->project_leader_id))
                                        <option value="{{ $project->project_leader_id }}">{{ $project->projectLeader->name }}</option>
                                    @endif
                                </select>
                                <input type="date" name="deadline" class="rounded-xl border-gray-200 bg-gray-50 text-sm focus:border-brand-500 focus:ring-brand-500">
                                <button type="submit" class="rounded-xl bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-200">Toevoegen</button>
                            </form>
                        @endcan
                    </div>
                @endif
            </section>
        @endforeach
    </div>

    {{-- ===== Tab: Overzicht ===== --}}
    <div x-show="tab === 'overzicht'" class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">

            {{-- Signalen --}}
            @php
                $signalen = collect([
                    $project->isOverdue() ? ['color' => 'red', 'text' => 'Verwachte einddatum ('.$project->end_date_expected->translatedFormat('j M').') is verstreken.'] : null,
                    $project->depositOutstanding() ? ['color' => 'amber', 'text' => 'Aanbetaling van € '.number_format((float) $project->deposit_amount, 0, ',', '.').' nog niet ontvangen'.($project->start_date ? ' — start '.$project->start_date->translatedFormat('j M') : '').'.'] : null,
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

            {{-- Status --}}
            <section class="rounded-2xl border border-gray-200 bg-white p-5">
                <h2 class="mb-3 text-sm font-bold text-navy-900">Status</h2>
                @cannot('manage-crm')
                    <x-status-badge :status="$project->status" />
                @endcannot
                @can('manage-crm')
                <form method="POST" action="{{ route('projects.status', $project) }}" class="flex flex-wrap items-center gap-2">
                    @csrf
                    @method('PATCH')
                    @foreach (\App\Enums\ProjectStatus::cases() as $statusOption)
                        <button type="submit" name="status" value="{{ $statusOption->value }}"
                                @disabled($statusOption === $project->status)
                                @if ($statusOption === \App\Enums\ProjectStatus::Afgerond && $project->status !== \App\Enums\ProjectStatus::Afgerond)
                                    onclick="return confirm('Project markeren als afgerond?');"
                                @endif
                                class="rounded-full px-3 py-1.5 text-xs font-semibold transition {{ $statusOption === $project->status ? 'cursor-default '.$statusOption->badgeClasses().' ring-2 ring-navy-900/20' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                            {{ $statusOption->label() }}
                        </button>
                    @endforeach
                </form>
                @endcan
                @if ($project->status === \App\Enums\ProjectStatus::Uitvoering)
                    <div class="mt-4">
                        <div class="mb-1 flex justify-between text-xs text-gray-500"><span>Voortgang</span><span>{{ $project->progress }}%</span></div>
                        <div class="h-2 overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full bg-brand-500" style="width: {{ $project->progress }}%"></div>
                        </div>
                    </div>
                @endif
            </section>

            {{-- Betalingen --}}
            @can('manage-crm')
            <section class="rounded-2xl border border-gray-200 bg-white p-5">
                <h2 class="mb-3 text-sm font-bold text-navy-900">Betalingen</h2>
                <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                    <div><dt class="text-gray-500">Projectwaarde</dt><dd class="text-base font-bold text-navy-900">€ {{ number_format((float) $project->value, 2, ',', '.') }}</dd></div>
                    <div><dt class="text-gray-500">Openstaand</dt><dd class="text-base font-bold {{ $project->outstandingAmount() > 0 ? 'text-brand-600' : 'text-green-600' }}">€ {{ number_format($project->outstandingAmount(), 2, ',', '.') }}</dd></div>
                    <div>
                        <dt class="text-gray-500">Aanbetaling</dt>
                        <dd class="font-medium">
                            {{ $project->deposit_amount ? '€ '.number_format((float) $project->deposit_amount, 2, ',', '.') : '—' }}
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
                    <div><dt class="text-gray-500">Volgende betaaltermijn</dt><dd class="font-medium">{{ $project->next_payment_due_at?->translatedFormat('j M Y') ?? '—' }}</dd></div>
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
                            class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 border-gray-300 transition hover:border-green-500 hover:bg-green-50"
                            title="Afronden"></button>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium text-navy-900">{{ $task->title }}</span>
                            <span class="text-xs text-gray-500">{{ $task->deadline?->translatedFormat('j M') ?? 'geen deadline' }} · {{ $task->owner?->name ?? '—' }}</span>
                        </span>
                        <span class="h-2 w-2 shrink-0 rounded-full {{ $task->priority->dotClasses() }}"></span>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">Geen open taken.</p>
                @endforelse
            </section>

            {{-- Documenten & foto's --}}
            <section class="rounded-2xl border border-gray-200 bg-white p-5">
                <h2 class="mb-3 text-sm font-bold text-navy-900">Documenten & foto's</h2>
                @forelse ($project->documents as $document)
                    <a href="{{ route('documents.show', $document) }}" class="mb-2 flex items-center gap-2 rounded-lg border border-gray-200 p-2.5 text-sm transition hover:border-brand-400">
                        <x-icon :name="$document->isImage() ? 'camera' : 'paper-clip'" class="text-gray-400" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium text-navy-900">{{ $document->name }}</span>
                            <span class="text-xs text-gray-500">{{ $document->category->label() }} · {{ $document->created_at->translatedFormat('j M Y') }} · {{ $document->uploader?->name ?? '—' }}</span>
                        </span>
                    </a>
                @empty
                    <p class="mb-3 text-sm text-gray-400">Nog geen documenten.</p>
                @endforelse

                <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="mt-3 grid gap-2 sm:grid-cols-3">
                    @csrf
                    <input type="hidden" name="documentable_type" value="project">
                    <input type="hidden" name="documentable_id" value="{{ $project->id }}">
                    <input type="file" name="file" required class="text-xs text-gray-500 file:mr-2 file:rounded-lg file:border-0 file:bg-navy-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-navy-700 sm:col-span-1">
                    <select name="category" class="rounded-lg border-gray-300 text-xs focus:border-brand-500 focus:ring-brand-500">
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
                            <a href="{{ route('customers.show', $project->customer) }}" class="text-steel-600 hover:text-steel-500">{{ $project->customer->name }}</a>
                        @else
                            {{ $project->customer->name }}
                        @endcan
                    </dd></div>
                    <div><dt class="text-gray-500">Adres</dt><dd class="font-medium">{{ $project->address ?? '—' }}, {{ $project->city ?? '' }}</dd></div>
                    <div><dt class="text-gray-500">Startdatum</dt><dd class="font-medium">{{ $project->start_date?->translatedFormat('j M Y') ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Verwachte oplevering</dt><dd class="font-medium {{ $project->isOverdue() ? 'text-red-600' : '' }}">{{ $project->end_date_expected?->translatedFormat('j M Y') ?? '—' }}</dd></div>
                    @if ($project->end_date_actual)
                        <div><dt class="text-gray-500">Opgeleverd</dt><dd class="font-medium text-green-700">{{ $project->end_date_actual->translatedFormat('j M Y') }}</dd></div>
                    @endif
                    <div><dt class="text-gray-500">Projectleider</dt><dd class="font-medium">{{ $project->projectLeader?->name ?? 'Niet toegewezen' }}</dd></div>
                    @can('manage-crm')
                        @if ($project->quote && config('renovion.modules.quotes'))
                            <div><dt class="text-gray-500">Offerte</dt><dd class="font-medium"><a href="{{ route('quotes.show', $project->quote) }}" class="text-steel-600 hover:text-steel-500">{{ $project->quote->number }}</a></dd></div>
                        @endif
                    @endcan
                </dl>
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
                    <p class="text-sm text-gray-400">Nog niemand ingepland.</p>
                @endforelse
            </section>

            {{-- Komende planning --}}
            <section class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-navy-900">Komende planning</h2>
                    <a href="{{ route('planning.index') }}" class="text-xs font-semibold text-steel-600 hover:text-steel-500">Planning →</a>
                </div>
                @forelse ($project->scheduleEntries as $entry)
                    <div class="mb-2 rounded-lg border p-2.5 text-sm {{ $entry->type->blockClasses() }}">
                        <p class="font-semibold">{{ $entry->date->translatedFormat('D j M') }}</p>
                        <p class="text-xs opacity-70">{{ $entry->user->name }}</p>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">Niets gepland.</p>
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
