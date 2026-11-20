@php
    use App\Enums\DocumentCategory;
@endphp

<div class="grid gap-4 lg:grid-cols-3">
    <div class="lg:col-span-2">
        @if ($documents->isEmpty())
            <x-empty-state icon="folder" category="klant" title="Nog geen documenten"
                           subtitle="Tekeningen, vergunningen en keuringsrapporten horen bij het dossier, niet in een mailbox." />
        @else
            <div class="overflow-hidden rounded-2xl border border-surface-700 bg-surface-800">
                @foreach ($documents as $document)
                    <a href="{{ route('documents.show', $document) }}"
                       class="flex items-center gap-3 border-b border-surface-700 p-4 transition last:border-b-0 hover:bg-ink/5">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-klant-500/15 text-klant-400">
                            <x-icon :name="$document->category->icon()" class="h-5 w-5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <b class="block truncate text-sm font-semibold text-ink">{{ $document->name }}</b>
                            <small class="block truncate text-xs text-muted">
                                {{ $document->category->label() }}, {{ $document->created_at->translatedFormat('j M Y') }}
                                @if ($document->uploader), {{ $document->uploader->name }} @endif
                            </small>
                        </span>
                        <x-icon name="arrow-down-tray" class="h-4 w-4 shrink-0 text-faint" />
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    @can('manage-crm')
        <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data"
              class="space-y-3 rounded-2xl border border-surface-700 bg-surface-800 p-5">
            @csrf
            <input type="hidden" name="documentable_type" value="project">
            <input type="hidden" name="documentable_id" value="{{ $project->id }}">

            <h2 class="text-sm font-bold text-ink">Document <span class="text-brand-500">toevoegen</span></h2>

            <input type="file" name="file" required
                   class="w-full text-xs text-muted file:mr-2 file:rounded-lg file:border-0 file:bg-brand-500/10 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-brand-300 hover:file:bg-brand-500/15">

            <select name="category" class="w-full rounded-xl border-surface-600 bg-surface-850 py-2 text-sm text-ink focus:border-brand-500 focus:ring-brand-500">
                @foreach (DocumentCategory::cases() as $category)
                    <option value="{{ $category->value }}">{{ $category->label() }}</option>
                @endforeach
            </select>

            <button type="submit" class="btn pri w-full">Uploaden</button>
        </form>
    @endcan
</div>
