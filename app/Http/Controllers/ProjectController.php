<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Enums\TimelineEventType;
use App\Enums\UserRole;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
        ]);

        $status = $request->filled('status') ? ProjectStatus::from($request->query('status')) : null;

        $projects = Project::with(['customer', 'projectLeader'])
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
        ]);
    }

    public function create(): View
    {
        return view('projects.create', [
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'city']),
            'leaders' => User::whereIn('role', [UserRole::Admin, UserRole::Projectleider, UserRole::Sales])
                ->orderBy('name')
                ->get(['id', 'name']),
            'vakmensen' => User::vakmensen()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $validated = $request->validated();

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

    public function show(Project $project): View
    {
        if (auth()->user()->cannot('manage-crm') && ! $project->craftsmen()->whereKey(auth()->id())->exists()) {
            abort(403);
        }

        $project->load([
            'customer',
            'lead',
            'quote',
            'projectLeader',
            'craftsmen',
            'tasks' => fn ($query) => $query->open()->orderByRaw('deadline is null, deadline asc'),
            'documents.uploader',
            'scheduleEntries' => fn ($query) => $query
                ->whereDate('date', '>=', today())
                ->orderBy('date')
                ->limit(10),
            'scheduleEntries.user',
        ]);

        return view('projects.show', ['project' => $project]);
    }

    public function edit(Project $project): View
    {
        $project->load(['customer', 'craftsmen']);

        return view('projects.edit', [
            'project' => $project,
            'leaders' => User::whereIn('role', [UserRole::Admin, UserRole::Projectleider, UserRole::Sales])
                ->orderBy('name')
                ->get(['id', 'name']),
            'vakmensen' => User::vakmensen()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $project) {
            $depositWasOutstanding = $project->depositOutstanding();

            $project->update(collect($validated)
                ->except(['deposit_received', 'craftsmen'])
                ->all());

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

        return redirect()->route('projects.show', $project)->with('success', 'Project bijgewerkt.');
    }
}
