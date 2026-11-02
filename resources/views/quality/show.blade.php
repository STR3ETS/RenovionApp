@php
    use App\Models\SnagItem;
    $blocker = $review->blockerFor($review->step);
    $open = $snags->filter->isOpen();
@endphp

<x-layouts.app :title="'Kwaliteit, '.$project->name">
    <div class="mb-4">
        <a href="{{ route('quality.index') }}" class="flex items-center gap-1 text-sm font-semibold text-slate-400 transition hover:text-white">
            <x-icon name="chevron-left" class="h-4 w-4" /> Kwaliteit en oplevering
        </a>
    </div>

    <x-page-header :title="$project->name" eyebrow="Kwaliteit en oplevering" category="project"
                   :subtitle="collect([$project->customer?->name, 'voortgang '.$project->progress.'%', $review->wkb ? 'valt onder de Wkb' : null])->filter()->join(', ')">
        <span class="rounded-full bg-project-500/15 px-3 py-1.5 text-xs font-bold text-project-300">
            Stap {{ $review->step + 1 }} van {{ count($flow) }}
        </span>
    </x-page-header>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            {{-- De opleverketen --}}
            <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-4 text-sm font-bold text-white">Oplever<span class="text-brand-500">keten</span></h2>

                <div class="space-y-2">
                    @foreach ($flow as $index => [$label, $condition])
                        <div class="flex items-start gap-3 rounded-xl border p-3 {{ $index === $review->step ? 'border-brand-500 bg-brand-500/5' : 'border-surface-700 bg-surface-850' }}">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $index < $review->step ? 'bg-green-500/20 text-green-300' : ($index === $review->step ? 'bg-brand-500 text-white' : 'bg-surface-700 text-slate-500') }}">
                                @if ($index < $review->step)
                                    <x-icon name="check" class="h-3.5 w-3.5" />
                                @else
                                    {{ $index + 1 }}
                                @endif
                            </span>

                            <span class="min-w-0 flex-1">
                                <b class="block text-sm font-semibold {{ $index <= $review->step ? 'text-white' : 'text-slate-400' }}">{{ $label }}</b>
                                <small class="block text-xs text-slate-500">{{ $condition }}</small>
                            </span>
                        </div>
                    @endforeach
                </div>

                @can('manage-crm')
                    @unless ($review->isDelivered())
                        <div class="mt-4 border-t border-surface-700 pt-4">
                            @if ($blocker !== '')
                                <div class="mb-3 flex items-start gap-2.5 rounded-xl border border-amber-500/25 bg-amber-500/10 p-3">
                                    <x-icon name="exclamation-triangle" class="h-5 w-5 shrink-0 text-amber-300" />
                                    <p class="text-sm text-slate-200">{{ $blocker }}</p>
                                </div>
                            @endif

                            <form method="POST" action="{{ route('quality.advance', $review) }}">
                                @csrf
                                <button type="submit" @disabled($blocker !== '')
                                        class="flex w-full items-center justify-center gap-1.5 rounded-xl bg-brand-500 py-3 text-sm font-bold text-white transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-40">
                                    <x-icon name="check" class="h-4 w-4" />
                                    {{ $flow[$review->step][0] }} afronden
                                </button>
                            </form>
                        </div>
                    @endunless
                @endcan
            </div>

            {{-- Controlepunten --}}
            <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-1 text-sm font-bold text-white">Controle<span class="text-brand-500">punten</span></h2>
                <p class="mb-4 text-xs text-slate-500">
                    Dit zijn de dingen die je achteraf niet meer kunt vaststellen.
                    {{ $review->applicableChecks()->filter->isChecked()->count() }} van {{ $review->applicableChecks()->count() }} afgevinkt.
                </p>

                <div class="space-y-1.5">
                    @foreach ($review->applicableChecks() as $check)
                        <div class="flex items-center gap-3 rounded-xl border border-surface-700 bg-surface-850 p-3">
                            @can('manage-crm')
                                <form method="POST" action="{{ route('quality-checks.toggle', $check) }}" class="shrink-0">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" title="{{ $check->isChecked() ? 'Weer open zetten' : 'Afvinken' }}"
                                            class="flex h-6 w-6 items-center justify-center rounded-full transition {{ $check->isChecked() ? 'bg-green-500/20 text-green-300' : 'bg-surface-700 text-slate-500 hover:bg-surface-600' }}">
                                        <x-icon name="check" class="h-3.5 w-3.5" />
                                    </button>
                                </form>
                            @else
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full {{ $check->isChecked() ? 'bg-green-500/20 text-green-300' : 'bg-surface-700 text-slate-500' }}">
                                    <x-icon name="check" class="h-3.5 w-3.5" />
                                </span>
                            @endcan

                            <span class="min-w-0 flex-1">
                                <b class="block text-sm {{ $check->isChecked() ? 'text-slate-300' : 'text-white' }}">{{ $check->title }}</b>
                                @if ($check->isChecked())
                                    <small class="block text-xs text-slate-500">
                                        {{ $check->checker?->name }}, {{ $check->checked_at->translatedFormat('j M H:i') }}
                                    </small>
                                @endif
                            </span>

                            @if ($check->wkb)
                                <span class="shrink-0 rounded bg-calc-500/15 px-1.5 py-0.5 text-[10px] font-bold text-calc-300">Wkb</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Restpunten --}}
            <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-1 text-sm font-bold text-white">Rest<span class="text-brand-500">punten</span></h2>
                <p class="mb-4 text-xs text-slate-500">
                    {{ $open->count() }} open van {{ $snags->count() }}. Een restpunt zonder locatie is onvindbaar voor wie het moet herstellen.
                </p>

                @if ($snags->isEmpty())
                    <p class="text-sm text-slate-500">Nog geen restpunten vastgelegd.</p>
                @else
                    <div class="space-y-2">
                        @foreach ($snags as $snag)
                            <div class="rounded-xl border bg-surface-850 p-3 {{ $snag->isOverdue() ? 'border-red-500/30' : 'border-surface-700' }}">
                                <div class="flex items-start gap-3">
                                    <span class="min-w-0 flex-1">
                                        <span class="flex flex-wrap items-center gap-2">
                                            <b class="truncate text-sm font-semibold text-white">{{ $snag->title }}</b>
                                            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $snag->badgeClasses() }}">{{ $snag->statusLabel() }}</span>
                                            @if ($snag->source === 'klant')
                                                <span class="rounded-full bg-klant-500/15 px-2 py-0.5 text-[10px] font-bold text-klant-300">Van de klant</span>
                                            @endif
                                        </span>
                                        <small class="mt-0.5 block text-xs text-slate-400">
                                            {{ $snag->location ?: 'geen locatie' }}, {{ $snag->ownerName() }}
                                            @if ($snag->due_on)
                                                , voor {{ $snag->due_on->translatedFormat('j M') }}
                                            @endif
                                        </small>
                                        @if ($snag->description)
                                            <small class="mt-1 block text-xs text-slate-400">{{ $snag->description }}</small>
                                        @endif
                                    </span>
                                </div>

                                @can('manage-crm')
                                    <div class="mt-2 flex flex-wrap gap-2 border-t border-surface-700 pt-2">
                                        @foreach (SnagItem::STATUSES as $key => $label)
                                            @continue($snag->status === $key)
                                            <form method="POST" action="{{ route('snags.update', $snag) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="{{ $key }}">
                                                <button type="submit" class="rounded-lg border border-surface-600 px-2.5 py-1.5 text-xs font-semibold text-slate-300 transition hover:text-white">
                                                    {{ $label }}
                                                </button>
                                            </form>
                                        @endforeach
                                        <form method="POST" action="{{ route('snags.destroy', $snag) }}" class="ml-auto">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-slate-600 transition hover:text-red-400"><x-icon name="trash" class="h-3.5 w-3.5" /></button>
                                        </form>
                                    </div>
                                @endcan
                            </div>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('snags.store', $project) }}" class="mt-4 space-y-2 border-t border-surface-700 pt-4">
                    @csrf
                    <div class="grid gap-2 sm:grid-cols-2">
                        <input type="text" name="title" required placeholder="Wat is er niet in orde?"
                               class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">
                        <input type="text" name="location" placeholder="Waar precies, bijvoorbeeld: badkamer, achterwand"
                               class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">
                    </div>

                    <div class="grid gap-2 sm:grid-cols-3">
                        <select name="assigned_to" class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                            <option value="">Eigen mens</option>
                            @foreach ($people as $person)
                                <option value="{{ $person->id }}">{{ $person->name }}</option>
                            @endforeach
                        </select>
                        <select name="subcontractor_id" class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                            <option value="">Of een partner</option>
                            @foreach ($partners as $partner)
                                <option value="{{ $partner->id }}">{{ $partner->name }}</option>
                            @endforeach
                        </select>
                        <input type="date" name="due_on" title="Herstellen voor"
                               class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                    </div>

                    <div class="flex items-center gap-2">
                        <label class="flex items-center gap-2 text-sm text-slate-300">
                            <input type="checkbox" name="source" value="klant"
                                   class="rounded border-surface-600 bg-surface-750 text-brand-500 focus:ring-brand-500">
                            Door de klant gemeld
                        </label>
                        <button type="submit" class="ml-auto rounded-xl bg-brand-500 px-4 py-2 text-sm font-bold text-white transition hover:bg-brand-600">
                            Restpunt vastleggen
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="space-y-4">
            {{-- Ondertekening --}}
            <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-3 text-sm font-bold text-white">Oplever<span class="text-brand-500">formulier</span></h2>

                @if ($review->signed_at)
                    <p class="flex items-center gap-2 text-sm font-semibold text-white">
                        <x-icon name="check" class="h-5 w-5 text-green-300" /> Getekend
                    </p>
                    <p class="mt-1 text-sm text-slate-300">{{ $review->signed_name }}</p>
                    <p class="text-xs text-slate-500">{{ $review->signed_at->translatedFormat('j F Y H:i') }}</p>
                @else
                    <p class="mb-3 text-sm text-slate-500">Nog niet getekend. Dit is voorwaarde voor stap zes.</p>

                    @can('manage-crm')
                        <form method="POST" action="{{ route('quality.sign', $review) }}" class="space-y-2">
                            @csrf
                            <input type="text" name="signed_name" required placeholder="Naam van wie tekent"
                                   class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">
                            <button type="submit" class="w-full rounded-xl bg-brand-500 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600">
                                Vastleggen
                            </button>
                        </form>
                    @endcan
                @endif
            </div>

            <div class="rounded-2xl border border-surface-700 bg-surface-800 p-5">
                <h2 class="mb-3 text-sm font-bold text-white">Dossier</h2>
                <div class="space-y-2 text-sm">
                    <a href="{{ route('projects.show', $project) }}" class="flex items-center gap-2 text-slate-200 transition hover:text-white">
                        <x-icon name="folder" class="h-4 w-4 text-slate-500" /> Projectdossier
                    </a>
                    <a href="{{ route('work-orders.index', ['project' => $project->id]) }}" class="flex items-center gap-2 text-slate-200 transition hover:text-white">
                        <x-icon name="wrench" class="h-4 w-4 text-slate-500" /> Werkbonnen
                    </a>
                    @if ($project->end_date_actual)
                        <p class="text-xs text-slate-500">
                            Opgeleverd {{ $project->end_date_actual->translatedFormat('j M Y') }},
                            garantie {{ $project->warranty_months }} maanden
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
