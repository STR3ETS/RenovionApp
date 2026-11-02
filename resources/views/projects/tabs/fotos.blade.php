@php
    // Filteren op fase gaat via de url, zodat een link naar een fase deelbaar is.
    $faseFilter = request()->integer('fase');
    $zichtbaar = $faseFilter ? $photos->where('project_phase_id', $faseFilter) : $photos;
@endphp

<div class="grid gap-4 lg:grid-cols-4">
    <div class="space-y-4">
        {{-- Filter per fase --}}
        <nav class="space-y-1 rounded-2xl border border-surface-700 bg-surface-800 p-2">
            <a href="{{ route('projects.show', [$project, 'tab' => 'fotos']) }}"
               class="flex items-center justify-between rounded-lg px-3 py-2 text-sm font-semibold transition {{ $faseFilter === 0 ? 'bg-brand-500 text-white' : 'text-slate-300 hover:bg-surface-850' }}">
                Alle foto's <span class="text-xs opacity-70">{{ $photos->count() }}</span>
            </a>
            @foreach ($phases as $phase)
                @php($aantal = $photos->where('project_phase_id', $phase->id)->count())
                @continue($aantal === 0)
                <a href="{{ route('projects.show', [$project, 'tab' => 'fotos', 'fase' => $phase->id]) }}"
                   class="flex items-center justify-between rounded-lg px-3 py-2 text-sm font-semibold transition {{ $faseFilter === $phase->id ? 'bg-brand-500 text-white' : 'text-slate-300 hover:bg-surface-850' }}">
                    <span class="truncate">{{ $phase->name }}</span> <span class="text-xs opacity-70">{{ $aantal }}</span>
                </a>
            @endforeach
        </nav>

        {{-- Uploaden --}}
        <form method="POST" action="{{ route('photos.store', $project) }}" enctype="multipart/form-data"
              class="space-y-3 rounded-2xl border border-surface-700 bg-surface-800 p-4">
            @csrf
            <h2 class="text-sm font-bold text-white">Foto's toevoegen</h2>
            <input type="file" name="photos[]" multiple required accept="image/*" capture="environment"
                   class="w-full text-xs text-slate-400 file:mr-2 file:rounded-lg file:border-0 file:bg-brand-500/10 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-brand-300 hover:file:bg-brand-500/15">
            <select name="project_phase_id" class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
                <option value="">Fase, optioneel</option>
                @foreach ($phases as $phase)
                    <option value="{{ $phase->id }}" @selected($project->currentPhase()?->is($phase))>{{ $phase->name }}</option>
                @endforeach
            </select>
            <input type="text" name="caption" maxlength="255" placeholder="Omschrijving, bijvoorbeeld leidingwerk badkamer"
                   class="w-full rounded-xl border-surface-600 bg-surface-850 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">
            <label class="flex items-center gap-2 text-xs font-medium text-slate-300">
                <input type="hidden" name="client_visible" value="0">
                <input type="checkbox" name="client_visible" value="1" checked class="rounded border-surface-600 bg-surface-750 text-brand-500 focus:ring-brand-500">
                Zichtbaar voor de klant
            </label>
            <button type="submit" class="w-full rounded-xl bg-brand-500 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600">Uploaden</button>
        </form>
    </div>

    <div class="lg:col-span-3">
        @if ($zichtbaar->isEmpty())
            <x-empty-state title="Nog geen foto's" icon="camera" category="project"
                           subtitle="Upload foto's per fase of vanaf een werkpakket, zo bouw je het bewijs voor de oplevering op." />
        @else
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                @foreach ($zichtbaar as $photo)
                    <figure class="group relative overflow-hidden rounded-xl border border-surface-700 bg-surface-800">
                        <img src="{{ route('photos.show', $photo) }}" alt="{{ $photo->caption ?? 'Projectfoto' }}" loading="lazy"
                             class="h-36 w-full object-cover sm:h-40">
                        <figcaption class="px-3 py-2">
                            <span class="block truncate text-xs font-semibold text-white">{{ $photo->caption ?? $photo->workPackage?->name ?? 'Projectfoto' }}</span>
                            <span class="block truncate text-[11px] text-slate-500">
                                {{ $photo->created_at->translatedFormat('j M') }}{{ $photo->uploader ? ', '.$photo->uploader->name : '' }}
                            </span>
                        </figcaption>
                        @unless ($photo->client_visible)
                            <span class="absolute top-2 left-2 rounded-full bg-navy-950/80 px-2 py-0.5 text-[10px] font-semibold text-white">Intern</span>
                        @endunless
                        @if (auth()->user()->can('manage-crm') || $photo->uploaded_by === auth()->id())
                            <form method="POST" action="{{ route('photos.destroy', $photo) }}" onsubmit="return confirm('Foto verwijderen?');"
                                  class="absolute top-2 right-2 opacity-0 transition group-hover:opacity-100">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="flex h-7 w-7 items-center justify-center rounded-full bg-surface-950/80 text-slate-300 shadow hover:text-red-400" title="Verwijderen">
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
