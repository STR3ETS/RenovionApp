{{-- Fasen met hun gate en de werkpakketten eronder (briefing paragraaf 7). --}}
<div class="space-y-3">
        @foreach ($project->phases as $phase)
            @php $isActief = $project->currentPhase()?->is($phase); @endphp
            <section class="rounded-2xl border bg-surface-800 {{ $isActief ? 'border-brand-300 ring-1 ring-brand-100' : 'border-surface-700' }}">
                <header class="flex flex-wrap items-center gap-3 px-5 py-4">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[11px] font-bold {{ $phase->status === \App\Enums\PhaseStatus::Gereed ? 'bg-green-500 text-white' : ($isActief ? 'bg-brand-500 text-white' : 'bg-surface-700 text-slate-400') }}">
                        @if ($phase->status === \App\Enums\PhaseStatus::Gereed)<x-icon name="check" class="h-3.5 w-3.5" />@else{{ $phase->position }}@endif
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold text-white">{{ $phase->name }}</span>
                        <span class="block text-xs text-slate-500">
                            {{ $phase->responsible?->name ?? 'Geen verantwoordelijke' }}
                            @if ($phase->approved_at), vrijgegeven door {{ $phase->approver?->name ?? '-' }} op {{ $phase->approved_at->translatedFormat('j M') }} @endif
                            @if ($phase->client_approved_at), klant: gezien en akkoord ({{ $phase->client_approved_at->translatedFormat('j M') }}) @endif
                        </span>
                    </span>

                    @can('manage-crm')
                        <form method="POST" action="{{ route('phases.update', $phase) }}">
                            @csrf @method('PATCH')
                            <select name="status" onchange="this.form.submit()" class="rounded-lg border-surface-700 py-1.5 text-xs font-semibold focus:border-brand-500 focus:ring-brand-500">
                                @foreach (\App\Enums\PhaseStatus::cases() as $statusOption)
                                    <option value="{{ $statusOption->value }}" @selected($phase->status === $statusOption)>{{ $statusOption->label() }}</option>
                                @endforeach
                            </select>
                        </form>
                        @if ($phase->status !== \App\Enums\PhaseStatus::Gereed)
                            <details class="relative">
                                <summary class="cursor-pointer rounded-lg bg-navy-950 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-navy-900">Vrijgeven</summary>
                                <form method="POST" action="{{ route('phases.approve', $phase) }}" class="absolute right-0 z-20 mt-2 w-64 space-y-2 rounded-xl border border-surface-700 bg-surface-800 p-3 shadow-lg">
                                    @csrf
                                    <p class="text-xs text-slate-400">Gate: alle werkpakketten in deze fase moeten gereed zijn.</p>
                                    <input type="text" name="gate_note" maxlength="255" placeholder="Bewijs/notitie (optioneel)" class="w-full rounded-lg border-surface-600 text-xs focus:border-brand-500 focus:ring-brand-500">
                                    <button type="submit" class="w-full rounded-lg bg-brand-500 py-2 text-xs font-bold text-white transition hover:bg-brand-600">Fase vrijgeven</button>
                                </form>
                            </details>
                        @endif
                    @else
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $phase->status->badgeClasses() }}">{{ $phase->status->label() }}</span>
                    @endcan
                </header>

                @if ($phase->workPackages->isNotEmpty() || auth()->user()->can('manage-crm'))
                    <div class="border-t border-surface-700 px-5 py-3">
                        <div class="space-y-1.5">
                            @foreach ($phase->workPackages as $package)
                                {{-- Vanuit de app naar het werkpakketscherm in de app zelf (session app_bundle). --}}
                                <a href="{{ session('app_bundle') ? session('app_bundle').'/#/werkpakket/'.$package->id : route('work-packages.show', $package) }}" class="flex items-center gap-3 rounded-xl border border-surface-700 p-2.5 transition hover:border-brand-400">
                                    <x-signal-dot :color="$package->isOverdue() ? 'red' : $package->status->dotColor()" />
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-semibold text-white {{ $package->isDone() ? 'text-slate-500 line-through' : '' }}">{{ $package->name }}</span>
                                        <span class="block text-xs text-slate-500">
                                            {{ $package->responsible?->name ?? 'Niet toegewezen' }}
                                            @if ($package->deadline), {{ $package->deadline->translatedFormat('j M') }} @endif
                                            @if ($package->items->isNotEmpty()), checklist {{ $package->items->filter->isDone()->count() }}/{{ $package->items->count() }} @endif
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
                                <input type="text" name="name" required placeholder="+ Werkpakket (bijv. Elektra begane grond)" class="min-w-48 flex-1 rounded-xl border-surface-700 bg-surface-850 text-sm placeholder:text-slate-500 focus:border-brand-500 focus:bg-surface-800 focus:ring-brand-500">
                                <select name="responsible_id" class="rounded-xl border-surface-700 bg-surface-850 text-sm focus:border-brand-500 focus:ring-brand-500">
                                    <option value="">Verantwoordelijke</option>
                                    @foreach ($project->craftsmen as $member)
                                        <option value="{{ $member->id }}">{{ $member->name }}</option>
                                    @endforeach
                                    @if ($project->projectLeader && ! $project->craftsmen->contains('id', $project->project_leader_id))
                                        <option value="{{ $project->project_leader_id }}">{{ $project->projectLeader->name }}</option>
                                    @endif
                                </select>
                                <input type="date" name="deadline" class="rounded-xl border-surface-700 bg-surface-850 text-sm focus:border-brand-500 focus:ring-brand-500">
                                <button type="submit" class="rounded-xl bg-surface-750 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-surface-700">Toevoegen</button>
                            </form>
                        @endcan
                    </div>
                @endif
            </section>
        @endforeach
</div>
