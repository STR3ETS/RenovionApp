@php
    use App\Enums\DocumentCategory;
@endphp

<x-layouts.app title="Documenten">
    <x-page-header title="Documenten" eyebrow="Organisatie" category="klant"
                   subtitle="Alles wat er aan papier omgaat, in vier logische groepen in plaats van twaalf losse mappen." />

    <form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
        <div class="relative min-w-0 flex-1 sm:max-w-xs">
            <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-500" />
            <input type="search" name="q" value="{{ $zoek }}" placeholder="Zoek op bestandsnaam"
                   class="w-full rounded-full border-surface-700 bg-surface-850 py-2 pl-10 text-sm text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500">
        </div>

        <select name="categorie" onchange="this.form.submit()"
                class="shrink-0 rounded-full border-surface-700 bg-surface-850 py-2 text-sm text-white focus:border-brand-500 focus:ring-brand-500">
            <option value="">Alle categorieën</option>
            @foreach (DocumentCategory::cases() as $cat)
                <option value="{{ $cat->value }}" @selected($categorie === $cat->value)>
                    {{ $cat->label() }} ({{ $counts[$cat->value] ?? 0 }})
                </option>
            @endforeach
        </select>
    </form>

    <x-tabs>
        <x-tab :href="route('documents.index', ['q' => $zoek ?: null])"
               :active="$groep === '' && $categorie === ''" :count="$counts->sum()">Alles</x-tab>
        @foreach ($groups as $group)
            @php
                $inGroup = collect(DocumentCategory::cases())
                    ->filter(fn ($c) => $c->group() === $group)
                    ->sum(fn ($c) => $counts[$c->value] ?? 0);
            @endphp
            @continue($inGroup === 0 && $groep !== $group)
            <x-tab :href="route('documents.index', ['groep' => $group, 'q' => $zoek ?: null])"
                   :active="$groep === $group" :count="$inGroup">{{ $group }}</x-tab>
        @endforeach
    </x-tabs>

    @if ($documents->isEmpty())
        <x-empty-state icon="folder" category="klant" title="Geen documenten gevonden"
                       subtitle="Documenten komen hier terecht zodra ze bij een klant, project of offerte worden geüpload." />
    @else
        <div class="overflow-hidden rounded-2xl border border-surface-700 bg-surface-800">
            @foreach ($documents as $document)
                <a href="{{ route('documents.show', $document) }}"
                   class="flex items-center gap-3 border-b border-surface-700 p-4 transition last:border-b-0 hover:bg-white/5">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-klant-500/15 text-klant-400">
                        <x-icon :name="$document->category->icon()" class="h-5 w-5" />
                    </span>

                    <span class="min-w-0 flex-1">
                        <b class="block truncate text-sm font-semibold text-white">{{ $document->name }}</b>
                        <small class="block truncate text-xs text-slate-400">
                            {{ $document->category->label() }}
                            @if ($document->customer)
                                , {{ $document->customer->name }}
                            @endif
                            , {{ $document->created_at->translatedFormat('j M Y') }}
                            @if ($document->uploader)
                                , {{ $document->uploader->name }}
                            @endif
                        </small>
                    </span>

                    <span class="shrink-0 text-right">
                        <small class="block text-xs text-slate-500">
                            {{ $document->size ? number_format($document->size / 1024, 0, ',', '.').' kB' : '' }}
                        </small>
                        <x-icon name="arrow-down-tray" class="ml-auto h-4 w-4 text-slate-500" />
                    </span>
                </a>
            @endforeach
        </div>

        <div class="mt-4">{{ $documents->links() }}</div>
    @endif
</x-layouts.app>
