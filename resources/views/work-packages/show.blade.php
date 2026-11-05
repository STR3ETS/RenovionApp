<x-layouts.app :title="$workPackage->name">

    {{-- Breadcrumb (mockup: Huissen > Uitvoering > Elektra) --}}
    <nav class="mb-3 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs font-semibold text-gray-600">
        <a href="{{ route('projects.show', $workPackage->project) }}" class="tap hover:text-brand-600">{{ $workPackage->project->name }}</a>
        <x-icon name="chevron-right" class="h-3 w-3" />
        <span>{{ $workPackage->phase->name }}</span>
    </nav>

    <x-page-header eyebrow="Werkpakket" category="project" :title="$workPackage->name">
        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $workPackage->status->badgeClasses() }}">{{ $workPackage->status->label() }}</span>
    </x-page-header>

    @php $checklistDone = $workPackage->items->filter->isDone()->count(); @endphp

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">

            {{-- Checklist (mockup: afvinkbare items + fototeller) --}}
            <section class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-navy-900">Checklist</h2>
                    @if ($workPackage->items->isNotEmpty())
                        <span class="text-xs font-semibold text-gray-500">{{ $checklistDone }}/{{ $workPackage->items->count() }}</span>
                    @endif
                </div>

                <div class="space-y-2">
                    @forelse ($workPackage->items as $item)
                        <div class="flex items-center gap-2 rounded-xl border pr-2 {{ $item->isDone() ? 'border-gray-100 bg-gray-50/60' : 'border-gray-200' }}">
                            {{-- De hele rij (vakje + tekst) is de afvinkknop: goed te raken met een duim of handschoen. --}}
                            <form method="POST" action="{{ route('checklist-items.toggle', [$workPackage, $item]) }}" class="min-w-0 flex-1">
                                @csrf @method('PATCH')
                                <button type="submit" @disabled($workPackage->isDone())
                                        class="group flex min-h-14 w-full items-center gap-3 rounded-xl p-3 text-left transition active:bg-gray-50 disabled:cursor-default"
                                        title="{{ $item->isDone() ? 'Weer openzetten' : 'Afvinken' }}">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border-2 transition {{ $item->isDone() ? 'border-brand-500 bg-brand-500 text-white' : 'border-gray-300 group-hover:border-brand-400' }} group-disabled:opacity-60">
                                        @if ($item->isDone())<x-icon name="check" class="h-4 w-4" />@endif
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-medium {{ $item->isDone() ? 'text-gray-500 line-through' : 'text-navy-900' }}">{{ $item->label }}</span>
                                        @if ($item->isDone())
                                            <span class="block text-xs text-gray-500">{{ $item->doneBy?->name ?? '-' }}, {{ $item->done_at->translatedFormat('j M H:i') }}</span>
                                        @endif
                                    </span>
                                </button>
                            </form>
                            @if ($item->requiresPhotos())
                                <span class="flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold whitespace-nowrap {{ $item->hasRequiredPhotos() ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}"
                                      title="{{ $item->photos->count() }} van minimaal {{ $item->requires_photos }} bewijsfoto's aanwezig">
                                    <x-icon name="camera" class="h-3 w-3" /> {{ $item->photos->count() }}/{{ $item->requires_photos }}
                                </span>
                                @unless ($item->hasRequiredPhotos() || $workPackage->isDone())
                                    <form method="POST" action="{{ route('photos.store', $workPackage->project) }}" enctype="multipart/form-data">
                                        @csrf
                                        <input type="hidden" name="work_package_id" value="{{ $workPackage->id }}">
                                        <input type="hidden" name="checklist_item_id" value="{{ $item->id }}">
                                        <label class="tap flex cursor-pointer items-center gap-1 rounded-lg bg-gray-100 px-2 py-1 text-[11px] font-semibold text-gray-600 transition hover:bg-gray-200" title="Bewijsfoto's uploaden">
                                            <x-icon name="camera" class="h-3.5 w-3.5" /> Upload
                                            <input type="file" name="photos[]" accept="image/*" capture="environment" multiple class="hidden" onchange="this.form.submit()">
                                        </label>
                                    </form>
                                @endunless
                            @endif
                            @can('manage-crm')
                                <form method="POST" action="{{ route('checklist-items.destroy', [$workPackage, $item]) }}" onsubmit="return confirm('Checklistitem verwijderen?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="flex h-10 w-10 items-center justify-center rounded-lg text-gray-300 transition hover:text-red-500 active:bg-red-50" title="Verwijderen"><x-icon name="trash" class="h-4 w-4" /></button>
                                </form>
                            @endcan
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Nog geen checklistitems.</p>
                    @endforelse
                </div>

                @can('manage-crm')
                    @unless ($workPackage->isDone())
                        {{-- Nieuw checklistitem: ingeklapt een knop, open één afgebakend blok met de velden bij elkaar. --}}
                        <div x-data="{ adding: false }" class="mt-3">
                            <button type="button" x-show="!adding" @click="adding = true; $nextTick(() => $refs.label.focus())"
                                    class="flex w-full items-center justify-center gap-1.5 rounded-xl border border-dashed border-gray-300 py-2.5 text-sm font-semibold text-gray-500 transition hover:border-brand-400 hover:text-brand-600 active:bg-gray-50">
                                <x-icon name="plus" class="h-4 w-4" /> Checklistitem toevoegen
                            </button>

                            <form method="POST" action="{{ route('checklist-items.store', $workPackage) }}" x-show="adding" x-cloak
                                  class="space-y-3 rounded-xl border border-gray-200 bg-gray-50 p-3">
                                @csrf
                                <p class="text-xs font-bold tracking-wide text-gray-600 uppercase">Nieuw checklistitem</p>

                                <label class="block">
                                    <span class="mb-1 block text-xs font-semibold text-gray-600">Wat moet er gebeuren?</span>
                                    <input x-ref="label" type="text" name="label" required placeholder="Bijv. Kitnaad douche bijwerken"
                                           class="w-full rounded-xl border-gray-200 bg-white text-sm placeholder:text-gray-400 focus:border-brand-500 focus:ring-brand-500">
                                </label>

                                <label class="flex items-center gap-3">
                                    <span class="min-w-0 flex-1">
                                        <span class="flex items-center gap-1.5 text-xs font-semibold text-gray-600"><x-icon name="camera" class="h-4 w-4 text-gray-500" /> Verplichte bewijsfoto's</span>
                                        <span class="block text-[11px] text-gray-500">0 = geen foto nodig om af te vinken</span>
                                    </span>
                                    <input type="number" name="requires_photos" value="0" min="0" max="20"
                                           class="w-20 shrink-0 rounded-xl border-gray-200 bg-white text-center text-sm focus:border-brand-500 focus:ring-brand-500">
                                </label>

                                <div class="flex gap-2">
                                    <button type="submit" class="flex-1 rounded-xl bg-brand-500 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600 active:bg-brand-600">Toevoegen</button>
                                    <button type="button" @click="adding = false" class="rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Annuleren</button>
                                </div>
                            </form>
                        </div>
                    @endunless
                @endcan
            </section>

            {{-- Foto's (briefing §9: bewijs gekoppeld aan het werkpakket) --}}
            <section class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-navy-900">Foto's</h2>
                    <span class="text-xs font-semibold text-gray-500">{{ $workPackage->photos->count() }}</span>
                </div>

                @if ($workPackage->photos->isNotEmpty())
                    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                        @foreach ($workPackage->photos as $photo)
                            <figure class="group relative overflow-hidden rounded-xl border border-gray-200">
                                <button type="button" class="block w-full cursor-zoom-in text-left" title="Foto bekijken"
                                        data-photo="{{ route('photos.show', $photo) }}" data-caption="{{ $photo->caption ?? $photo->checklistItem?->label ?? 'Bewijsfoto' }}"
                                        data-meta="{{ collect([$photo->created_at->translatedFormat('j M Y H:i'), $photo->uploader?->name])->filter()->implode(', ') }}">
                                    <img src="{{ route('photos.show', $photo) }}" alt="{{ $photo->caption ?? 'Bewijsfoto' }}" loading="lazy" class="h-32 w-full object-cover">
                                    <span class="block px-2.5 py-1.5">
                                        <span class="block truncate text-[11px] font-semibold text-navy-900">{{ $photo->caption ?? $photo->checklistItem?->label ?? 'Bewijsfoto' }}</span>
                                        <span class="block truncate text-[10px] text-gray-500">{{ $photo->created_at->translatedFormat('j M H:i') }}{{ $photo->uploader ? ', '.$photo->uploader->name : '' }}</span>
                                    </span>
                                </button>
                                @unless ($photo->client_visible)
                                    <span class="absolute top-1.5 left-1.5 rounded-full bg-navy-950/80 px-2 py-0.5 text-[10px] font-semibold text-white">Intern</span>
                                @endunless
                                @if (auth()->user()->can('manage-crm') || $photo->uploaded_by === auth()->id())
                                    <form method="POST" action="{{ route('photos.destroy', $photo) }}" onsubmit="return confirm('Foto verwijderen?');"
                                          class="absolute top-1.5 right-1.5 transition pointer-fine:opacity-0 pointer-fine:group-hover:opacity-100">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="tap flex h-6 w-6 items-center justify-center rounded-full bg-white/90 text-gray-500 shadow hover:text-red-600" title="Verwijderen">
                                            <x-icon name="trash" class="h-3 w-3" />
                                        </button>
                                    </form>
                                @endif
                            </figure>
                        @endforeach
                    </div>
                @endif

                {{-- Foto-bewijs: grote cameraknop in plaats van het native bestandsveld (dat op mobiel als afgekapt "Ch" toonde) --}}
                <form method="POST" action="{{ route('photos.store', $workPackage->project) }}" enctype="multipart/form-data" class="space-y-2"
                      x-data="{ files: [] }">
                    @csrf
                    <input type="hidden" name="work_package_id" value="{{ $workPackage->id }}">
                    <x-photo-button />
                    <div class="flex gap-2 app:flex-col">
                        <input type="text" name="caption" maxlength="255" placeholder="Omschrijving (optioneel)" aria-label="Omschrijving" class="min-w-0 flex-1 rounded-xl border-gray-200 bg-gray-50 text-sm focus:border-brand-500 focus:bg-white focus:ring-brand-500">
                        <button type="submit" :disabled="!files.length" class="rounded-xl bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-200 disabled:opacity-40">Uploaden</button>
                    </div>
                </form>
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
                          @unless ($workPackage->checklistComplete() && $workPackage->evidenceComplete()) onsubmit="return confirm('De checklist of het foto-bewijs is nog niet compleet. Toch proberen af te ronden?');" @endunless>
                        @csrf
                        {{-- Oranje (actie) pas als het kan; tot die tijd grijs, zodat hij niet concurreert met "Foto maken". --}}
                        <button type="submit" class="w-full rounded-xl py-3.5 text-base font-bold transition {{ $workPackage->checklistComplete() && $workPackage->evidenceComplete() ? 'bg-brand-500 text-white hover:bg-brand-600' : 'bg-gray-200 text-gray-500' }}">
                            Taak afronden
                        </button>
                    </form>
                    @unless ($workPackage->checklistComplete())
                        <p class="mt-2 text-xs text-gray-500">Kan pas afgerond worden als de checklist compleet is ({{ $checklistDone }}/{{ $workPackage->items->count() }}).</p>
                    @endunless
                    @if ($workPackage->checklistComplete() && ! $workPackage->evidenceComplete())
                        <p class="mt-2 text-xs text-amber-700">Het verplichte foto-bewijs is nog niet compleet - upload eerst de ontbrekende foto's.</p>
                    @endif
                @endif
            </section>

            {{-- Meta (mockup: verantwoordelijke, deadline, fase, project) --}}
            <section class="rounded-2xl border border-gray-200 bg-white p-5">
                <h2 class="mb-3 text-sm font-bold text-navy-900">Details</h2>
                <dl class="space-y-2 text-sm">
                    <div><dt class="text-gray-500">Verantwoordelijke</dt><dd class="font-medium">{{ $workPackage->responsible?->name ?? 'Niet toegewezen' }}</dd></div>
                    <div><dt class="text-gray-500">Deadline</dt><dd class="font-medium {{ $workPackage->isOverdue() ? 'text-red-600' : '' }}">{{ $workPackage->deadline?->translatedFormat('j M Y') ?? '-' }}</dd></div>
                    <div><dt class="text-gray-500">Fase</dt><dd class="font-medium">{{ $workPackage->phase->name }}</dd></div>
                    <div><dt class="text-gray-500">Project</dt><dd class="font-medium"><a href="{{ route('projects.show', $workPackage->project) }}" class="text-brand-700 hover:text-brand-500">{{ $workPackage->project->name }}</a></dd></div>
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
                                <option value="">- Niet toegewezen -</option>
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
                        <button type="submit" class="w-full rounded-xl border border-red-200 py-2 text-sm font-semibold text-red-600 transition hover:bg-red-50">Verwijderen</button>
                    </form>
                </section>
            @endcan
        </div>
    </div>

</x-layouts.app>
