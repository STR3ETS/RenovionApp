<?php

namespace App\Http\Controllers\Api\App;

use App\Enums\TimelineEventType;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Quote;
use App\Models\TimelineEvent;
use App\Support\AppPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Klanten in de app: de lijst en het klantdossier, met dezelfde gegevens als customers/index en show. */
class CustomerController extends Controller
{
    /** Tot zoveel klanten in één keer; daarboven zoek je. */
    private const LIMIT = 200;

    public function index(Request $request): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        $search = trim((string) $request->query('q'));
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search).'%';

        $customers = Customer::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', $like)
                ->orWhere('city', 'like', $like)
                ->orWhere('email', 'like', $like)))
            ->withCount(['leads', 'projects'])
            ->orderBy('name')
            ->limit(self::LIMIT + 1)
            ->get();

        return response()->json([
            'total' => $search === '' ? Customer::count() : null,
            'more' => $customers->count() > self::LIMIT,
            'customers' => $customers->take(self::LIMIT)->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'initial' => str($customer->name)->replace('Familie ', '')->substr(0, 1)->upper()->toString(),
                'city' => $customer->city,
                'phone' => $customer->phone,
                'leads' => $customer->leads_count,
                'projects' => $customer->projects_count,
                'path' => route('customers.show', $customer, false),
            ])->values(),
        ]);
    }

    public function show(Customer $customer): JsonResponse
    {
        return response()->json($this->dossier($customer));
    }

    /** Notitie in de timeline, net als het veld boven de timeline op de website. Geeft het dossier terug. */
    public function storeNote(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $customer->recordEvent(TimelineEventType::Notitie, 'Notitie', $validated['body']);

        return response()->json($this->dossier($customer->fresh()), 201);
    }

    /** @return array<string, mixed> */
    private function dossier(Customer $customer): array
    {
        $quotes = (bool) config('renovion.modules.quotes');

        $customer->load([
            'leads' => fn ($query) => $query->latest(),
            'projects' => fn ($query) => $query->latest(),
            'documents' => fn ($query) => $query->latest(),
            'timelineEvents' => fn ($query) => $query->with('user')->limit(50),
            'portalUser',
            ...($quotes ? ['quotes' => fn ($query) => $query->latest()] : []),
        ]);

        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'address' => $customer->address,
            'postal_city' => trim(($customer->postal_code ?? '').' '.($customer->city ?? '')) ?: null,
            'subtitle' => trim(($customer->address ? $customer->address.', ' : '').($customer->city ?? '')) ?: null,
            'portal' => $customer->portalUser !== null,
            'leads' => $customer->leads->map(fn (Lead $lead) => [
                'id' => $lead->id,
                'name' => $lead->service ?? 'Aanvraag',
                'status' => ['label' => $lead->status->label(), 'classes' => $lead->status->appBadgeClasses()],
                'path' => route('leads.show', $lead, false),
            ]),
            'quotes' => $quotes ? $customer->quotes->map(fn (Quote $quote) => [
                'id' => $quote->id,
                'number' => $quote->number,
                'total' => (float) $quote->total,
                'status' => ['label' => $quote->status->label(), 'classes' => $quote->status->appBadgeClasses()],
                'path' => route('quotes.show', $quote, false),
            ]) : null,
            'projects' => $customer->projects->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'status' => ['label' => $project->status->label(), 'classes' => $project->status->appBadgeClasses()],
                'path' => route('projects.show', $project, false),
            ]),
            'documents' => $customer->documents->map(fn (Document $document) => [
                'id' => $document->id,
                'name' => $document->name,
                'meta' => $document->category->label().', '.$document->created_at->translatedFormat('j M Y'),
                'path' => route('documents.show', $document, false),
            ]),
            'timeline' => $customer->timelineEvents->map(fn (TimelineEvent $event) => AppPayload::timelineEvent($event)),
            'links' => [
                'web' => route('customers.show', $customer, false),
                'new_lead' => route('leads.create', ['klant' => $customer->id], false),
            ],
        ];
    }
}
