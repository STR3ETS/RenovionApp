<?php

namespace App\Http\Controllers;

use App\Actions\BuildFinalSettlement;
use App\Enums\PhaseStatus;
use App\Enums\ProjectStatus;
use App\Enums\TimelineEventType;
use App\Enums\UserRole;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\AgentApproval;
use App\Models\AgentEvent;
use App\Models\AgentRun;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Intake;
use App\Models\Project;
use App\Models\ProjectDeduction;
use App\Models\ProjectPhase;
use App\Models\QualityReview;
use App\Models\Rfq;
use App\Models\User;
use App\Services\CostReviewService;
use App\Services\ProjectHealthService;
use App\Support\ProjectTabs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProjectController extends Controller
{
    public function index(Request $request, ProjectHealthService $healthService): View
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
        ]);

        $status = $request->filled('status') ? ProjectStatus::from($request->query('status')) : null;

        $projects = Project::with(['customer', 'projectLeader', 'craftsmen', 'phases'])
            ->withCount(['photos' => fn ($query) => $query->where('client_visible', true)])
            ->when($request->user()->cannot('manage-crm'), fn ($query) => $query->whereHas(
                'craftsmen', fn ($craftsmen) => $craftsmen->where('users.id', $request->user()->id)
            ))
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when($status === null, fn ($query) => $query->active())
            ->orderByRaw('start_date is null, start_date asc')
            ->paginate(25)
            ->withQueryString();

        return view('projects.index', [
            'projects' => $projects,
            'status' => $status,
            'health' => $healthService->forMany($projects->getCollection()),
        ]);
    }

    public function create(): View
    {
        return view('projects.create', [
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'city']),
            'leaders' => User::whereIn('role', [UserRole::Admin, UserRole::Projectleider, UserRole::Werkvoorbereider, UserRole::Sales])
                ->orderBy('name')
                ->get(['id', 'name']),
            'uitvoerders' => User::uitvoerders()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['cover_photo_path'] = $request->file('cover_photo')?->store('project-covers');

        $project = DB::transaction(function () use ($validated) {
            $customer = isset($validated['customer_id'])
                ? Customer::findOrFail($validated['customer_id'])
                : Customer::create([
                    'name' => $validated['customer_name'],
                    'email' => $validated['customer_email'] ?? null,
                    'phone' => $validated['customer_phone'] ?? null,
                    'address' => $validated['customer_address'] ?? null,
                    'postal_code' => $validated['customer_postal_code'] ?? null,
                    'city' => $validated['customer_city'] ?? null,
                ]);

            $project = Project::create([
                'customer_id' => $customer->id,
                'name' => $validated['name'],
                'address' => $validated['address'] ?? $customer->address,
                'city' => $validated['city'] ?? $customer->city,
                'status' => $validated['status'] ?? ProjectStatus::Voorbereiding,
                'value' => $validated['value'] ?? 0,
                'deposit_amount' => $validated['deposit_amount'] ?? null,
                'start_date' => $validated['start_date'] ?? null,
                'end_date_expected' => $validated['end_date_expected'] ?? null,
                'project_leader_id' => $validated['project_leader_id'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'cover_photo_path' => $validated['cover_photo_path'],
            ]);

            $project->craftsmen()->sync($validated['craftsmen'] ?? []);

            $customer->recordEvent(
                TimelineEventType::Projectupdate,
                'Project aangemaakt: '.$project->name,
                null,
                $project,
            );

            AuditLog::record($project, 'aangemaakt', [], ['value' => (string) $project->value]);

            return $project;
        });

        return redirect()->route('projects.show', $project)->with('success', 'Project "'.$project->name.'" aangemaakt.');
    }

    public function show(Request $request, Project $project, ProjectHealthService $healthService): View
    {
        abort_unless($project->isAccessibleBy(auth()->user()), 403);

        // Wat elk tabblad nodig heeft, laden we pas als dat tabblad open staat.
        // Anders haalt één projectdossier het halve systeem op.
        $project->load([
            'customer', 'lead', 'quote', 'projectLeader', 'craftsmen', 'chatChannel',
            'phases.responsible', 'phases.approver',
        ]);

        $tab = ProjectTabs::resolve($request->user(), $project, $request->string('tab')->toString());

        return view('projects.show', [
            'project' => $project,
            'health' => $healthService->for($project),
            'tab' => $tab,
            'tabs' => ProjectTabs::forUser($request->user(), $project, $tab),
            'nextStep' => ProjectTabs::nextStep($project),
            ...$this->tabData($project, $tab),
        ]);
    }

    /**
     * De data van één tabblad.
     *
     * @return array<string, mixed>
     */
    private function tabData(Project $project, string $tab): array
    {
        return match ($tab) {
            'intake' => [
                'intake' => Intake::where('project_id', $project->id)
                    ->orWhere('lead_id', $project->lead_id)
                    ->with('surveyor')
                    ->first(),
            ],
            'begroting' => [
                'budgets' => $project->budgets()->with(['chapters.paragraphs.lines', 'markups', 'options', 'terms'])->get(),
            ],
            'planning' => [
                'entries' => $project->scheduleEntries()->with('user')->orderBy('date')->get(),
            ],
            'uitvoering' => [
                'phases' => $project->phases()->with(['responsible', 'approver', 'workPackages.responsible', 'workPackages.items'])->get(),
            ],
            'taken' => [
                'tasks' => $project->tasks()->with('owner')->orderByRaw('completed_at is null desc, deadline is null, deadline asc')->get(),
            ],
            'werkbonnen' => [
                'workOrders' => $project->workOrders()->with(['user', 'approver'])->get(),
            ],
            'fotos' => [
                'photos' => $project->photos()->with(['uploader', 'workPackage', 'phase'])->get(),
                'phases' => $project->phases,
            ],
            'inkoop' => [
                'orders' => $project->purchaseOrders()->with(['lines', 'invoices'])->get(),
                'rfqs' => Rfq::where('project_id', $project->id)->with(['bids.subcontractor', 'winner'])->get(),
            ],
            'kwaliteit' => [
                'review' => QualityReview::where('project_id', $project->id)->with('checks')->first(),
                'snags' => $project->snags()->with(['assignee', 'subcontractor'])->latest()->get(),
                'serviceTickets' => $project->serviceTickets()->get(),
            ],
            'financien' => [
                'invoices' => $project->invoices()->get(),
                'settlement' => app(BuildFinalSettlement::class)->preview($project),
                'deductions' => ProjectDeduction::where('project_id', $project->id)->get(),
            ],
            'nacalculatie' => [
                'review' => app(CostReviewService::class)->for($project),
            ],
            'documenten' => [
                'documents' => $project->documents()->with('uploader')->latest()->get(),
            ],
            'activiteiten' => [
                'events' => $project->customer
                    ->timelineEvents()
                    ->latest('happened_at')
                    ->limit(60)
                    ->get(),
            ],
            'agents' => [
                'runs' => AgentRun::where('project_id', $project->id)->latest('id')->get(),
                'approvals' => AgentApproval::where('project_id', $project->id)->latest('id')->get(),
                'auditEvents' => AgentEvent::where('project_id', $project->id)->latest('occurred_at')->limit(40)->get(),
            ],
            default => [
                'photos' => $project->photos()->where('client_visible', true)->limit(6)->get(),
                'tasks' => $project->tasks()->open()->with('owner')->orderByRaw('deadline is null, deadline asc')->limit(6)->get(),
                'entries' => $project->scheduleEntries()->with('user')->whereDate('date', '>=', today())->orderBy('date')->limit(6)->get(),
                'deliveryReport' => $project->deliveryReport,
                'events' => $project->customer->timelineEvents()->latest('happened_at')->limit(8)->get(),
            ],
        };
    }

    public function edit(Project $project): View
    {
        $project->load(['customer', 'craftsmen', 'phases']);

        return view('projects.edit', [
            'project' => $project,
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'city']),
            'leaders' => User::whereIn('role', [UserRole::Admin, UserRole::Projectleider, UserRole::Werkvoorbereider, UserRole::Sales])
                ->orderBy('name')
                ->get(['id', 'name']),
            'uitvoerders' => User::uitvoerders()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $validated = $request->validated();

        $previousCoverPath = $project->cover_photo_path;

        if ($request->hasFile('cover_photo')) {
            $validated['cover_photo_path'] = $request->file('cover_photo')->store('project-covers');
        }

        DB::transaction(function () use ($validated, $project) {
            $depositWasOutstanding = $project->depositOutstanding();

            $project->update(collect($validated)
                ->except(['deposit_received', 'craftsmen', 'current_phase', 'cover_photo'])
                ->all());

            if (array_key_exists('current_phase', $validated)) {
                $this->moveToPhase($project, (int) $validated['current_phase']);
            }

            if ($project->wasChanged('customer_id')) {
                $project->unsetRelation('customer');
                $project->customer->recordEvent(
                    TimelineEventType::Projectupdate,
                    'Project "'.$project->name.'" aan dit dossier gekoppeld',
                    null,
                    $project,
                );
            }

            if (array_key_exists('deposit_received', $validated)) {
                $project->forceFill([
                    'deposit_received_at' => $validated['deposit_received']
                        ? ($project->deposit_received_at ?? now())
                        : null,
                ])->save();
            }

            if (array_key_exists('craftsmen', $validated)) {
                $project->craftsmen()->sync($validated['craftsmen'] ?? []);
            }

            if ($depositWasOutstanding && $project->deposit_received_at !== null) {
                $project->customer->recordEvent(
                    TimelineEventType::Betaling,
                    'Aanbetaling ontvangen: € '.number_format((float) $project->deposit_amount, 2, ',', '.'),
                    null,
                    $project,
                );
            }

            if ($project->wasChanged()) {
                AuditLog::record($project, 'bijgewerkt', [], $project->getChanges());
            }
        });

        if (isset($validated['cover_photo_path']) && $previousCoverPath !== null && $previousCoverPath !== $project->cover_photo_path) {
            Storage::delete($previousCoverPath);
        }

        return redirect()->route('projects.show', $project)->with('success', 'Project bijgewerkt.');
    }

    /**
     * Streamt de omslagfoto vanuit private storage (geen storage:link nodig op live).
     */
    public function coverPhoto(Project $project): BinaryFileResponse
    {
        abort_unless($project->isAccessibleBy(auth()->user()) || $project->isViewableByClient(auth()->user()), 403);

        $path = $project->coverPhotoPath();

        abort_if($path === null || ! Storage::exists($path), 404);

        return response()->file(Storage::path($path), [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /**
     * Zet het project in de gekozen fase: alles ervoor gereed, alles erna terug
     * naar niet gestart. Fijnmazig fasebeheer (blokkades, wachten op klant)
     * volgt in de uitvoeringsmodule.
     */
    private function moveToPhase(Project $project, int $position): void
    {
        $project->phases()->get()->each(function (ProjectPhase $phase) use ($position) {
            $phase->update(match (true) {
                $phase->position < $position => [
                    'status' => PhaseStatus::Gereed,
                    'completed_at' => $phase->completed_at ?? now(),
                ],
                $phase->position === $position => [
                    'status' => in_array($phase->status, [PhaseStatus::Gereed, PhaseStatus::NietGestart], true)
                        ? PhaseStatus::Bezig
                        : $phase->status,
                    'completed_at' => null,
                ],
                default => [
                    'status' => PhaseStatus::NietGestart,
                    'completed_at' => null,
                ],
            });
        });

        $project->syncProgress();
    }
}
