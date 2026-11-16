<?php

namespace App\Http\Controllers\Api\App;

use App\Enums\LeadQualification;
use App\Enums\ProjectStatus;
use App\Enums\QuoteStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\PriceItem;
use App\Models\Project;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use App\Services\CalculationAssistant;
use App\Support\BudgetChapterLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * De formulieren in de app (mobile/app/screens/*formulier.*): keuzelijsten, standaardwaarden en de huidige
 * waarden van wat je bewerkt, in dezelfde vorm als de webformulieren (leads/create, projects/edit enz.).
 * Opslaan gaat via dezelfde controllers als de website (LeadController@store enz.), die JSON geven aan de app.
 */
class FormController extends Controller
{
    /** Diensten uit de datalist van het webformulier (leads/create). */
    private const SERVICES = ['Complete renovatie', 'Badkamerrenovatie', 'Toiletrenovatie', 'Stucwerk', 'Schilderwerk', 'Tegelwerk', 'Timmerwerk'];

    public function leadCreate(Request $request): JsonResponse
    {
        $customerId = $request->integer('klant') ?: null;

        return response()->json([
            'customers' => $this->customers(),
            'users' => $this->users(User::orderBy('name')->get(['id', 'name'])),
            'services' => self::SERVICES,
            'customer_id' => $customerId && Customer::whereKey($customerId)->exists() ? $customerId : null,
            'cancel' => route('leads.index', absolute: false),
        ]);
    }

    public function leadEdit(Lead $lead): JsonResponse
    {
        $lead->load('customer');
        $customer = $lead->customer;

        return response()->json([
            'title' => 'Bewerken: '.$customer->name,
            'users' => $this->users(User::orderBy('name')->get(['id', 'name'])),
            'services' => self::SERVICES,
            'qualifications' => $this->options(LeadQualification::cases()),
            'phone_missing' => blank($customer->phone),
            'values' => [
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'postal_code' => $customer->postal_code,
                'city' => $customer->city,
                'service' => $lead->service,
                'value' => $lead->value !== null ? (float) $lead->value : null,
                'qualification' => $lead->qualification->value,
                'desired_start' => $lead->desired_start,
                'description' => $lead->description,
                'assigned_to' => $lead->assigned_to,
                'last_contact_at' => $lead->last_contact_at?->format('Y-m-d\TH:i'),
                'next_action' => $lead->next_action,
                'next_action_at' => $lead->next_action_at?->format('Y-m-d\TH:i'),
            ],
            'cancel' => route('leads.show', $lead, false),
        ]);
    }

    public function projectCreate(): JsonResponse
    {
        return response()->json([
            ...$this->projectOptions(),
            'statuses' => $this->options(ProjectStatus::cases()),
            'cancel' => route('projects.index', absolute: false),
        ]);
    }

    public function projectEdit(Project $project): JsonResponse
    {
        $project->load(['customer', 'craftsmen', 'phases']);

        return response()->json([
            ...$this->projectOptions(),
            'title' => 'Bewerken: '.$project->name,
            'subtitle' => $project->customer->name,
            'phases' => $project->phases->map(fn ($phase) => ['value' => $phase->position, 'label' => $phase->position.'. '.$phase->name])->values(),
            'has_cover' => $project->cover_photo_path !== null,
            'values' => [
                'customer_id' => $project->customer_id,
                'name' => $project->name,
                'address' => $project->address,
                'city' => $project->city,
                'start_date' => $project->start_date?->toDateString(),
                'end_date_expected' => $project->end_date_expected?->toDateString(),
                'project_leader_id' => $project->project_leader_id,
                'current_phase' => $project->currentPhase()?->position,
                'notes' => $project->notes,
                'craftsmen' => $project->craftsmen->pluck('id')->all(),
                'value' => $project->value !== null ? (float) $project->value : null,
                'deposit_amount' => $project->deposit_amount !== null ? (float) $project->deposit_amount : null,
                'paid_amount' => $project->paid_amount !== null ? (float) $project->paid_amount : null,
                'next_payment_due_at' => $project->next_payment_due_at?->toDateString(),
                'deposit_received' => $project->deposit_received_at !== null,
            ],
            'cancel' => route('projects.show', $project, false),
        ]);
    }

    public function quoteCreate(Request $request): JsonResponse
    {
        $lead = $request->filled('aanvraag') ? Lead::with('customer')->find($request->integer('aanvraag')) : null;

        return response()->json([
            'customers' => $this->customers(),
            'lead' => $lead ? ['id' => $lead->id, 'customer_id' => $lead->customer_id, 'name' => $lead->customer->name] : null,
            'valid_until' => now()->addDays(30)->toDateString(),
            'cancel' => $lead ? route('leads.show', $lead, false) : route('quotes.index', absolute: false),
        ]);
    }

    public function quoteEdit(Quote $quote): JsonResponse
    {
        if ($quote->status !== QuoteStatus::Concept) {
            return response()->json(['message' => 'Alleen conceptoffertes kunnen worden bewerkt.'], 422);
        }

        $quote->load(['lines', 'customer']);

        return response()->json([
            'title' => 'Bewerken: '.$quote->number,
            'subtitle' => $quote->customer->name,
            'can_propose' => app(CalculationAssistant::class)->isConfigured(),
            'values' => [
                'valid_until' => $quote->valid_until?->toDateString(),
                'notes' => $quote->notes,
                'lines' => $quote->lines->map(fn (QuoteLine $line) => [
                    'description' => $line->description,
                    'quantity' => (float) $line->quantity,
                    'unit' => $line->unit,
                    'unit_price' => (float) $line->unit_price,
                    'vat_rate' => (int) $line->vat_rate,
                    'is_estimate' => (bool) $line->is_estimate,
                ])->values(),
            ],
            'cancel' => route('quotes.show', $quote, false),
        ]);
    }

    public function calculationCreate(Request $request): JsonResponse
    {
        $lead = $request->filled('aanvraag') ? Lead::with('customer')->find($request->integer('aanvraag')) : null;

        return response()->json([
            'customers' => $this->customers(),
            'lead' => $lead ? [
                'id' => $lead->id,
                'name' => $lead->customer->name,
                'sub' => collect([$lead->service ?? 'Aanvraag', $lead->customer->city ?? '-'])->implode(', '),
            ] : null,
            'sources' => PriceItem::active()->select('source', 'edition')->distinct()->orderBy('source')->get()
                ->map(fn (PriceItem $item) => ['source' => $item->source, 'edition' => $item->edition])->values(),
            'can_propose' => app(CalculationAssistant::class)->isConfigured(),
            'values' => [
                'title' => $lead ? 'Calculatie '.($lead->service ?? 'renovatie').' - '.$lead->customer->name : '',
                'customer_id' => $lead?->customer_id,
                'lead_id' => $lead?->id,
                'description' => $lead?->description ?? '',
            ],
            'cancel' => $lead ? route('leads.show', $lead, false) : route('calculations.index', absolute: false),
        ]);
    }

    public function budgetCreate(Request $request): JsonResponse
    {
        return response()->json([
            'projects' => Project::with('customer')->orderBy('name')->get()
                ->map(fn (Project $project) => ['id' => $project->id, 'name' => $project->name.($project->customer ? ', '.$project->customer->name : '')])->values(),
            'leads' => Lead::open()->with('customer')->orderByDesc('created_at')->get()
                ->map(fn (Lead $lead) => ['id' => $lead->id, 'name' => $lead->customer?->name ?? $lead->name])->values(),
            'templates' => collect(BudgetChapterLibrary::templateNames())->map(fn (string $name) => ['value' => $name, 'label' => ucfirst($name)])->values(),
            'values' => [
                'project_id' => $request->integer('project') ?: null,
                'lead_id' => $request->integer('aanvraag') ?: null,
                'hourly_rate' => 55,
                'vat_pct' => 21,
            ],
            'cancel' => route('budgets.index', absolute: false),
        ]);
    }

    public function rfqCreate(Request $request): JsonResponse
    {
        $projects = Project::active()->with('customer')->orderBy('name')->get();
        $projectId = $request->integer('project');

        return response()->json([
            'projects' => $projects->map(fn (Project $project) => ['id' => $project->id, 'name' => $project->name.($project->customer ? ', '.$project->customer->name : '')])->values(),
            'chapters' => collect(BudgetChapterLibrary::CHAPTERS)->map(fn (string $name, string $code) => ['value' => (string) $code, 'label' => $code.' '.$name])->values(),
            // Zoals de keuzelijst op de website: zonder keuze staat het eerste project geselecteerd.
            'values' => [
                'project_id' => $projects->contains('id', $projectId) ? $projectId : $projects->first()?->id,
                'deadline' => today()->addWeek()->toDateString(),
            ],
            'cancel' => route('rfqs.index', absolute: false),
        ]);
    }

    public function teamCreate(): JsonResponse
    {
        return response()->json([
            'roles' => $this->options(UserRole::internal()),
            'values' => ['role' => UserRole::Uitvoerder->value],
            'cancel' => route('team.index', absolute: false),
        ]);
    }

    public function teamEdit(Request $request, User $user): JsonResponse
    {
        return response()->json([
            'title' => 'Bewerken: '.$user->name,
            'roles' => $this->options(UserRole::internal()),
            'self' => $user->is($request->user()),
            'values' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role->value,
            ],
            'cancel' => route('team.index', absolute: false),
        ]);
    }

    /** @return array<string, mixed> */
    private function projectOptions(): array
    {
        return [
            'customers' => $this->customers(),
            'leaders' => $this->users(User::whereIn('role', [UserRole::Admin, UserRole::Projectleider, UserRole::Werkvoorbereider, UserRole::Sales])
                ->orderBy('name')->get(['id', 'name'])),
            'uitvoerders' => $this->users(User::uitvoerders()->orderBy('name')->get(['id', 'name'])),
        ];
    }

    private function customers(): Collection
    {
        return Customer::orderBy('name')->get(['id', 'name', 'city'])
            ->map(fn (Customer $customer) => ['id' => $customer->id, 'name' => $customer->name.' ('.($customer->city ?? '-').')'])
            ->values();
    }

    private function users(Collection $users): Collection
    {
        return $users->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])->values();
    }

    /**
     * @param  array<int, \BackedEnum>  $cases
     * @return list<array{value: string, label: string}>
     */
    private function options(array $cases): array
    {
        return array_map(fn ($case) => ['value' => $case->value, 'label' => $case->label()], $cases);
    }
}
