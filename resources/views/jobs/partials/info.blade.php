{{-- Alles wat je op de bouwplaats bij de hand wilt hebben: wat moet er gebeuren,
     wie je belt en welke tekeningen erbij horen. --}}
<div class="grid gap-3 lg:grid-cols-2">
    <div class="rounded-2xl border border-surface-700 bg-surface-800 p-4">
        <p class="mb-2 text-[11px] font-bold tracking-widest text-faint uppercase">Werkomschrijving</p>
        <p class="text-sm font-semibold text-ink">{{ $entry->displayTitle() }}</p>
        @if ($entry->notes)
            <p class="mt-2 text-sm whitespace-pre-line text-ink2">{{ $entry->notes }}</p>
        @endif
        @if ($project?->notes)
            <p class="mt-2 text-sm whitespace-pre-line text-muted">{{ $project->notes }}</p>
        @endif
    </div>

    <div class="rounded-2xl border border-surface-700 bg-surface-800 p-4">
        <p class="mb-3 text-[11px] font-bold tracking-widest text-faint uppercase">Contact</p>

        @if ($project?->projectLeader)
            <div class="mb-2 flex items-center gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-500/15 text-xs font-bold text-brand-300">
                    {{ str($project->projectLeader->name)->substr(0, 1)->upper() }}
                </span>
                <span class="min-w-0 flex-1">
                    <b class="block truncate text-sm font-semibold text-ink">{{ $project->projectLeader->name }}</b>
                    <small class="block text-xs text-faint">Projectleider</small>
                </span>
                @if ($project->projectLeader->phone)
                    <a href="tel:{{ str_replace(' ', '', $project->projectLeader->phone) }}"
                       class="btn sm">
                        <x-icon name="phone" class="h-3.5 w-3.5" /> Bel
                    </a>
                @endif
            </div>
        @endif

        @if ($project?->customer)
            <div class="flex items-center gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-klant-500/15 text-xs font-bold text-klant-300">
                    {{ str($project->customer->name)->substr(0, 1)->upper() }}
                </span>
                <span class="min-w-0 flex-1">
                    <b class="block truncate text-sm font-semibold text-ink">{{ $project->customer->name }}</b>
                    <small class="block text-xs text-faint">Klant</small>
                </span>
                @if ($project->customer->phone)
                    <a href="tel:{{ str_replace(' ', '', $project->customer->phone) }}"
                       class="btn sm">
                        <x-icon name="phone" class="h-3.5 w-3.5" /> Bel
                    </a>
                @endif
            </div>
        @endif
    </div>

    @if ($project && $project->documents->isNotEmpty())
        <div class="rounded-2xl border border-surface-700 bg-surface-800 p-4 lg:col-span-2">
            <p class="mb-3 text-[11px] font-bold tracking-widest text-faint uppercase">Tekeningen en documenten</p>
            <div class="space-y-1.5">
                @foreach ($project->documents->take(8) as $doc)
                    <a href="{{ route('documents.show', $doc) }}"
                       class="flex items-center justify-between gap-3 rounded-lg px-2 py-2 transition hover:bg-ink/5">
                        <span class="flex min-w-0 items-center gap-2">
                            <x-icon name="document-text" class="h-4 w-4 shrink-0 text-faint" />
                            <span class="truncate text-sm text-ink">{{ $doc->name }}</span>
                        </span>
                        <small class="shrink-0 text-xs text-faint">{{ $doc->category?->label() }}</small>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
