<x-layouts.app title="Klantportaal">
    <x-page-header title="Klantportaal" eyebrow="Projecten" category="klant"
                   subtitle="Wat de klant ziet, van binnenuit te openen. Handig voordat je iets vrijgeeft." />

    @if ($rows->isEmpty())
        <x-empty-state icon="home" category="klant" title="Geen lopende projecten"
                       subtitle="Zodra er een project loopt, kun je hier meekijken met de klant." />
    @else
        <div class="space-y-3">
            @foreach ($rows as $row)
                @php($project = $row['project'])
                <a href="{{ route('portal-preview.show', $project) }}"
                   class="motion-lift flex flex-wrap items-center gap-3 rounded-2xl border border-surface-700 bg-surface-800 p-4 transition hover:border-surface-600">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-klant-500/15 text-klant-400">
                        <x-icon name="home" class="h-5 w-5" />
                    </span>

                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center gap-2">
                            <b class="truncate text-sm font-bold text-white">{{ $project->name }}</b>
                            @unless ($row['hasAccount'])
                                <span class="rounded-full bg-amber-500/15 px-2 py-0.5 text-[10px] font-bold text-amber-300">Geen portaalaccount</span>
                            @endunless
                            @if ($row['waiting']->isNotEmpty())
                                <span class="rounded-full bg-brand-500/15 px-2 py-0.5 text-[10px] font-bold text-brand-300">
                                    Wacht op de klant
                                </span>
                            @endif
                        </span>
                        <small class="mt-0.5 block truncate text-xs text-slate-400">
                            {{ $project->customer?->name }},
                            voortgang {{ $project->progress }}%,
                            {{ $project->client_photos_count }} {{ $project->client_photos_count === 1 ? 'foto' : "foto's" }} zichtbaar
                            @if ($project->hidden_photos_count > 0)
                                , {{ $project->hidden_photos_count }} verborgen
                            @endif
                        </small>
                    </span>

                    <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-slate-500" />
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.app>
