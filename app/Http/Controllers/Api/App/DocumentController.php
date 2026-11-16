<?php

namespace App\Http\Controllers\Api\App;

use App\Enums\DocumentCategory;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Quote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Documenten in de app (mobile/app/screens/documenten.*): de bibliotheek zoals documents/index (zoeken,
 * categorie, groepen met tellers, 40 per keer) en één document met wat het is en waar het bij hoort. Het
 * bestand zelf opent via een ondertekend adres (de app heeft geen sessiecookie), inline zodat een foto of
 * PDF direct te zien is.
 */
class DocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = $request->string('q')->toString();
        $category = $request->string('categorie')->toString();
        $group = $request->string('groep')->toString();

        // Dezelfde filters als DocumentController@index van de website.
        $query = Document::with(['customer', 'uploader'])->latest()
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->when($group !== '', fn ($query) => $query->whereIn('category', collect(DocumentCategory::cases())
                ->filter(fn (DocumentCategory $item) => $item->group() === $group)
                ->map->value
                ->values()));

        $documents = $query->paginate(40);
        $counts = Document::selectRaw('category, count(*) as total')->groupBy('category')->pluck('total', 'category');
        $countOf = fn (DocumentCategory $item) => (int) ($counts[$item->value] ?? 0);

        return response()->json([
            'total' => $documents->total(),
            'page' => $documents->currentPage(),
            'more' => $documents->hasMorePages(),
            'all' => (int) $counts->sum(),
            // Een groep zonder documenten valt weg, behalve als die net gekozen is (zoals de tabs op de website).
            'groups' => collect(DocumentCategory::groups())
                ->map(fn (string $name) => ['value' => $name, 'label' => $name, 'count' => collect(DocumentCategory::cases())->filter(fn ($item) => $item->group() === $name)->sum($countOf)])
                ->filter(fn (array $item) => $item['count'] > 0 || $item['value'] === $group)
                ->values(),
            'categories' => array_map(fn (DocumentCategory $item) => ['value' => $item->value, 'label' => $item->label(), 'count' => $countOf($item)], DocumentCategory::cases()),
            'documents' => $documents->getCollection()->map(fn (Document $document) => [
                'id' => $document->id,
                'name' => $document->name,
                'icon' => $document->category->icon(),
                'sub' => collect([$document->category->label(), $document->customer?->name, $document->created_at->translatedFormat('j M Y'), $document->uploader?->name])->filter()->implode(', '),
                'size' => $this->size($document),
            ])->values(),
        ]);
    }

    /**
     * Eén document. Openen mag iedereen van het team (zoals documents.show op de website); de links naar klant,
     * aanvraag en offerte alleen voor kantoor, een project voor wie dat project mag zien.
     */
    public function show(Request $request, Document $document): JsonResponse
    {
        $document->load(['customer', 'uploader', 'documentable']);
        $crm = $request->user()->can('manage-crm');
        $owner = $document->documentable;

        return response()->json([
            'id' => $document->id,
            'name' => $document->name,
            'icon' => $document->category->icon(),
            'category' => $document->category->label(),
            'group' => $document->category->group(),
            'size' => $this->size($document),
            'mime' => $document->mime,
            'kind' => match (true) {
                $document->isImage() => 'image',
                $document->mime === 'application/pdf' || str_ends_with(strtolower($document->name), '.pdf') => 'pdf',
                default => 'file',
            },
            'uploaded' => collect([$document->created_at->translatedFormat('j M Y H:i'), $document->uploader?->name])->filter()->implode(', '),
            'customer' => $crm && $document->customer ? ['name' => $document->customer->name, 'path' => route('customers.show', $document->customer, false)] : null,
            'project' => $owner instanceof Project && $owner->isAccessibleBy($request->user()) ? ['name' => $owner->name, 'path' => route('projects.show', $owner, false)] : null,
            'belongs_to' => match (true) {
                $owner instanceof Project && $owner->isAccessibleBy($request->user()) => ['label' => 'Project', 'name' => $owner->name, 'path' => route('projects.show', $owner, false)],
                ! $crm => null,
                $owner instanceof Quote && Route::has('quotes.show') => ['label' => 'Offerte', 'name' => $owner->number, 'path' => route('quotes.show', $owner, false)],
                $owner instanceof Lead => ['label' => 'Aanvraag', 'name' => $owner->service ?? 'Aanvraag', 'path' => route('leads.show', $owner, false)],
                default => null,
            },
            // Twee tot drie uur geldig, en binnen het uur hetzelfde adres: dan blijft het bewaarde scherm in de app gelijk.
            'url' => URL::temporarySignedRoute('api.app.documents.file', $expires = now()->startOfHour()->addHours(3), $document, absolute: false),
            'download_url' => URL::temporarySignedRoute('api.app.documents.file', $expires, ['document' => $document, 'download' => 1], absolute: false),
        ]);
    }

    /** Het bestand: zonder token, met een ondertekend adres uit show(). Inline, of als download met ?download=1. */
    public function file(Request $request, Document $document): StreamedResponse
    {
        abort_unless(Storage::exists($document->path), 404);

        return Storage::response($document->path, $document->name, ['Cache-Control' => 'private, max-age=3600'], $request->boolean('download') ? 'attachment' : 'inline');
    }

    private function size(Document $document): ?string
    {
        return $document->size ? number_format($document->size / 1024, 0, ',', '.').' kB' : null;
    }
}
