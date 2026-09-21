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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
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
