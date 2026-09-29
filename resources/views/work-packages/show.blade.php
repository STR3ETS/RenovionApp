<x-layouts.app :title="$workPackage->name">

    {{-- Breadcrumb (mockup: Huissen > Uitvoering > Elektra) --}}
    <nav class="mb-3 flex items-center gap-1.5 text-xs font-semibold text-gray-400">
        <a href="{{ route('projects.show', $workPackage->project) }}" class="hover:text-brand-600">{{ $workPackage->project->name }}</a>
        <x-icon name="chevron-right" class="h-3 w-3" />
        <span>{{ $workPackage->phase->name }}</span>
        <x-icon name="chevron-right" class="h-3 w-3" />
        <span class="text-navy-900">{{ $workPackage->name }}</span>
    </nav>

    <x-page-header :title="$workPackage->name" :subtitle="$workPackage->project->name.' · '.$workPackage->phase->name">
        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $workPackage->status->badgeClasses() }}">{{ $workPackage->status->label() }}</span>
    </x-page-header>

    @php $checklistDone = $workPackage->items->filter->isDone()->count(); @endphp

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">

            {{-- Checklist (mockup: afvinkbare items + fototeller) --}}
            <section class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-navy-900">Checklist</h2>
                    @if ($workPackage->items->isNotEmpty())
                        <span class="text-xs font-semibold text-gray-400">{{ $checklistDone }}/{{ $workPackage->items->count() }}</span>
                    @endif
                </div>

                <div class="space-y-2">
                    @forelse ($workPackage->items as $item)
                        <div class="flex items-center gap-3 rounded-xl border p-3 {{ $item->isDone() ? 'border-gray-100 bg-gray-50/60' : 'border-gray-200' }}">
                            <form method="POST" action="{{ route('checklist-items.toggle', [$workPackage, $item]) }}">
                                @csrf @method('PATCH')
                                <button type="submit" @disabled($workPackage->isDone())
                                        class="flex h-6 w-6 items-center justify-center rounded-md border-2 transition {{ $item->isDone() ? 'border-brand-500 bg-brand-500 text-white' : 'border-gray-300 hover:border-brand-400' }} disabled:opacity-60"
                                        title="{{ $item->isDone() ? 'Weer openzetten' : 'Afvinken' }}">
                                    @if ($item->isDone())<x-icon name="check" class="h-3.5 w-3.5" />@endif
                                </button>
                            </form>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-medium {{ $item->isDone() ? 'text-gray-400 line-through' : 'text-navy-900' }}">{{ $item->label }}</span>
                                @if ($item->isDone())
                                    <span class="block text-xs text-gray-400">{{ $item->doneBy?->name ?? '—' }} · {{ $item->done_at->translatedFormat('j M H:i') }}</span>
                                @endif
                            </span>
                            @if ($item->requiresPhotos())
                                <span class="flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold whitespace-nowrap text-amber-800" title="Minimaal {{ $item->requires_photos }} bewijsfoto's vereist">
                                    <x-icon name="camera" class="h-3 w-3" /> min. {{ $item->requires_photos }}
                                </span>
                            @endif
                            @can('manage-crm')
                                <form method="POST" action="{{ route('checklist-items.destroy', [$workPackage, $item]) }}" onsubmit="return confirm('Checklistitem verwijderen?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="rounded p-1 text-gray-300 transition hover:text-red-500" title="Verwijderen"><x-icon name="trash" class="h-3.5 w-3.5" /></button>
                                </form>
                            @endcan
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">Nog geen checklistitems.</p>
                    @endforelse
                </div>

                @can('manage-crm')
                    @unless ($workPackage->isDone())
                        <form method="POST" action="{{ route('checklist-items.store', $workPackage) }}" class="mt-3 flex flex-wrap gap-2">
                            @csrf
                            <input type="text" name="label" required placeholder="+ Checklistitem toevoegen" class="min-w-48 flex-1 rounded-xl border-gray-200 bg-gray-50 text-sm placeholder:text-gray-400 focus:border-brand-500 focus:bg-white focus:ring-brand-500">
                            <label class="flex items-center gap-1.5 text-xs text-gray-500" title="Minimaal aantal bewijsfoto's (handhaving volgt bij foto-bewijs)">
                                <x-icon name="camera" class="h-4 w-4 text-gray-400" />
                                <input type="number" name="requires_photos" value="0" min="0" max="20" class="w-16 rounded-lg border-gray-200 py-1.5 text-xs focus:border-brand-500 focus:ring-brand-500">
                            </label>
                            <button type="submit" class="rounded-xl bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-200">Toevoegen</button>
                        </form>
                    @endunless
                @endcan
            </section>

            @if ($workPackage->description)
                <section class="rounded-2xl border border-gray-200 bg-white p-5">
                    <h2 class="mb-2 text-sm font-bold text-navy-900">Omschrijving</h2>
                    <p class="text-sm whitespace-pre-line text-gray-600">{{ $workPackage->description }}</p>
                </section>
            @endif
        </div>

        <div class="space-y-4">
            {{-- Afronden (mockup: grote oranje knop) --}}
            <section class="rounded-2xl border border-gray-200 bg-white p-5">
                @if ($workPackage->isDone())
                    <p class="mb-3 flex items-center gap-2 text-sm font-semibold text-green-700"><x-icon name="check" class="h-4 w-4" /> Afgerond op {{ $workPackage->completed_at->translatedFormat('j M Y H:i') }}</p>
                    <form method="POST" action="{{ route('work-packages.reopen', $workPackage) }}">
                        @csrf
                        <button type="submit" class="w-full rounded-xl border border-gray-300 bg-white py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Heropenen</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('work-packages.complete', $workPackage) }}"
                          @unless ($workPackage->checklistComplete()) onsubmit="return confirm('Nog niet alle checklistitems zijn afgevinkt. Toch proberen af te ronden?');" @endunless>
                        @csrf
                        <button type="submit" class="w-full rounded-xl bg-brand-500 py-3.5 text-base font-bold text-white transition hover:bg-brand-600">
                            Taak afronden
                        </button>
                    </form>
                    @unless ($workPackage->checklistComplete())
                        <p class="mt-2 text-xs text-gray-400">Kan pas afgerond worden als de checklist compleet is ({{ $checklistDone }}/{{ $workPackage->items->count() }}).</p>
                    @endunless
                @endif
            </section>

            {{-- Meta (mockup: verantwoordelijke, deadline, fase, project) --}}
            <section class="rounded-2xl border border-gray-200 bg-white p-5">
                <h2 class="mb-3 text-sm font-bold text-navy-900">Details</h2>
                <dl class="space-y-2 text-sm">
                    <div><dt class="text-gray-500">Verantwoordelijke</dt><dd class="font-medium">{{ $workPackage->responsible?->name ?? 'Niet toegewezen' }}</dd></div>
                    <div><dt class="text-gray-500">Deadline</dt><dd class="font-medium {{ $workPackage->isOverdue() ? 'text-red-600' : '' }}">{{ $workPackage->deadline?->translatedFormat('j M Y') ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Fase</dt><dd class="font-medium">{{ $workPackage->phase->name }}</dd></div>
                    <div><dt class="text-gray-500">Project</dt><dd class="font-medium"><a href="{{ route('projects.show', $workPackage->project) }}" class="text-brand-600 hover:text-brand-500">{{ $workPackage->project->name }}</a></dd></div>
                    <div><dt class="text-gray-500">Klant</dt><dd class="font-medium">{{ $workPackage->project->customer->name }}</dd></div>
                </dl>
            </section>

            {{-- Beheer --}}
            @can('manage-crm')
                <section class="rounded-2xl border border-gray-200 bg-white p-5">
                    <h2 class="mb-3 text-sm font-bold text-navy-900">Beheer</h2>
                    <form method="POST" action="{{ route('work-packages.update', $workPackage) }}" class="space-y-3">
                        @csrf @method('PATCH')
                        <x-field label="Naam" name="name">
                            <input type="text" name="name" id="name" value="{{ old('name', $workPackage->name) }}" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        </x-field>
                        <x-field label="Verantwoordelijke" name="responsible_id">
                            <select name="responsible_id" id="responsible_id" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="">— Niet toegewezen —</option>
                                @foreach ($team as $member)
                                    <option value="{{ $member->id }}" @selected(old('responsible_id', $workPackage->responsible_id) == $member->id)>{{ $member->name }}</option>
                                @endforeach
                            </select>
                        </x-field>
                        <x-field label="Deadline" name="deadline">
                            <input type="date" name="deadline" id="deadline" value="{{ old('deadline', $workPackage->deadline?->toDateString()) }}" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        </x-field>
                        <x-field label="Status" name="status">
                            <select name="status" id="status" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                                @foreach (\App\Enums\PhaseStatus::cases() as $statusOption)
                                    <option value="{{ $statusOption->value }}" @selected($workPackage->status === $statusOption)>{{ $statusOption->label() }}</option>
                                @endforeach
                            </select>
                        </x-field>
                        <x-field label="Omschrijving" name="description">
                            <textarea name="description" id="description" rows="2" class="w-full rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">{{ old('description', $workPackage->description) }}</textarea>
                        </x-field>
                        <button type="submit" class="rounded-xl border border-gray-300 bg-white px-5 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Opslaan</button>
                    </form>
                    <form method="POST" action="{{ route('work-packages.destroy', $workPackage) }}" class="mt-3" onsubmit="return confirm('Werkpakket verwijderen, inclusief checklist?');">
                        @csrf @method('DELETE')
                        <button type="submit" class="w-full rounded-xl border border-red-200 py-2 text-sm font-semibold text-red-500 transition hover:bg-red-50">Verwijderen</button>
                    </form>
                </section>
            @endcan
        </div>
    </div>

</x-layouts.app>
