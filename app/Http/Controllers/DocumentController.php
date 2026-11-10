<?php

namespace App\Http\Controllers;

use App\Enums\DocumentCategory;
use App\Enums\TimelineEventType;
use App\Http\Requests\StoreDocumentRequest;
use App\Models\Customer;
use App\Models\Document;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    /**
     * De documentenbibliotheek: alles wat er in het bedrijf aan papier omgaat,
     * gegroepeerd in vier logische groepen in plaats van twaalf losse mappen.
     */
    public function index(Request $request): View
    {
        $query = Document::with(['customer', 'uploader'])->latest();

        if ($zoek = $request->string('q')->toString()) {
            $query->where('name', 'like', "%{$zoek}%");
        }

        if ($categorie = $request->string('categorie')->toString()) {
            $query->where('category', $categorie);
        }

        if ($group = $request->string('groep')->toString()) {
            $inGroup = collect(DocumentCategory::cases())
                ->filter(fn (DocumentCategory $c) => $c->group() === $group)
                ->map->value;

            $query->whereIn('category', $inGroup);
        }

        $documents = $query->paginate(40)->withQueryString();

        return view('documents.index', [
            'documents' => $documents,
            'zoek' => $zoek,
            'categorie' => $categorie,
            'groep' => $group,
            'groups' => DocumentCategory::groups(),
            'counts' => Document::selectRaw('category, count(*) as total')
                ->groupBy('category')
                ->pluck('total', 'category'),
        ]);
    }

    public function store(StoreDocumentRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        /** @var class-string<Model> $modelClass */
        $modelClass = Relation::getMorphedModel($validated['documentable_type']);
        $documentable = $modelClass::query()->findOrFail($validated['documentable_id']);

        $file = $request->file('file');
        $path = $file->store('documents');

        $customerId = $documentable instanceof Customer
            ? $documentable->id
            : $documentable->customer_id ?? null;

        $document = Document::create([
            'documentable_type' => $validated['documentable_type'],
            'documentable_id' => $documentable->getKey(),
            'customer_id' => $customerId,
            'uploaded_by' => Auth::id(),
            'name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'category' => $validated['category'] ?? DocumentCategory::Overig,
        ]);

        $document->customer?->recordEvent(
            TimelineEventType::Document,
            'Document geüpload: '.$document->name,
            null,
            $document->documentable,
        );

        return back()->with('success', 'Document geüpload.');
    }

    public function show(Document $document): StreamedResponse
    {
        return Storage::download($document->path, $document->name);
    }
}
