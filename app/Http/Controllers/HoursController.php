<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\BudgetLine;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\RegieMaterial;
use App\Models\Setting;
use App\Models\TimeEntry;
use App\Support\RegieStatement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * De urenstaat van een project. Bij aangenomen werk staan de uren er ter
 * informatie. Bij regie zijn uren, tarief en materiaal aan te passen en
 * rekenen we ze af, maar pas nadat iemand de uren heeft afgetekend.
 */
class HoursController extends Controller
{
    /** Het overzicht voor het kantoor: alle urenstaten op een rij. */
    public function index(Request $request): View
    {
        $form = $request->string('vorm')->toString();

        $projects = Project::query()
            ->whereHas('timeEntries')
            ->when(array_key_exists($form, Project::FORMS), fn ($query) => $query->where('contract_form', $form))
            ->with(['customer', 'timeEntries', 'regieMaterials', 'hoursSigner'])
            ->latest('id')
            ->get();

        $rows = $projects->map(fn (Project $project) => [
            'project' => $project,
            'statement' => RegieStatement::for($project),
        ]);

        return view('hours.index', [
            'rows' => $rows,
            'form' => $form,
            'counts' => Project::whereHas('timeEntries')->selectRaw('contract_form, count(*) as total')->groupBy('contract_form')->pluck('total', 'contract_form'),
            'toInvoice' => round($rows->sum(fn (array $row) => $row['statement']->isRegie() ? $row['statement']->toInvoice() : 0), 2),
            'unsigned' => $rows->filter(fn (array $row) => ! $row['statement']->isSigned() || $row['statement']->needsResign())->count(),
            'hours' => round($rows->sum(fn (array $row) => $row['statement']->hours()), 2),
        ]);
    }

    /** De urenstaat om uit te draaien en te laten aftekenen. */
    public function print(Request $request, Project $project): View
    {
        abort_unless($project->isAccessibleBy($request->user()), 403);

        return view('hours.print', [
            'project' => $project->load('customer'),
            'statement' => RegieStatement::for($project),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        abort_unless($project->isAccessibleBy($request->user()), 403);

        $request->merge(['hours' => BudgetLine::parseNumber($request->input('hours'))]);

        $validated = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'hours' => ['required', 'numeric', 'min:0.25', 'max:24'],
            'description' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'exists:users,id'],
        ], [
            'date.before_or_equal' => 'Uren boek je achteraf, niet vooruit.',
        ]);

        // Wie het werk niet verdeelt, boekt alleen zijn eigen uren.
        $userId = $request->user()->can('manage-crm')
            ? ($validated['user_id'] ?? $request->user()->id)
            : $request->user()->id;

        $project->timeEntries()->create([
            'user_id' => $userId,
            'date' => $validated['date'],
            'hours' => $validated['hours'],
            'description' => $validated['description'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Uren toegevoegd aan de urenstaat.');
    }

    public function destroy(Request $request, TimeEntry $entry): RedirectResponse
    {
        $own = $entry->user_id === $request->user()->id && $entry->work_order_id === null;

        abort_unless($request->user()->can('manage-crm') || $own, 403);

        if ($entry->isInvoiced()) {
            return back()->with('error', 'Deze uren staan al op een factuur en kunnen niet meer weg.');
        }

        $entry->delete();

        return back()->with('success', 'Uren verwijderd.');
    }

    /** Aangenomen of regie, met het tarief waartegen we afrekenen. */
    public function contract(Request $request, Project $project): RedirectResponse
    {
        foreach (['regie_hourly_rate', 'regie_day_rate'] as $field) {
            $request->merge([$field => $request->filled($field) ? BudgetLine::parseNumber($request->input($field)) : null]);
        }

        $validated = $request->validate([
            'contract_form' => ['required', 'in:'.implode(',', array_keys(Project::FORMS))],
            'regie_hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'regie_day_rate' => ['nullable', 'numeric', 'min:0', 'max:8000'],
        ]);

        // Zonder eigen afspraak geldt het standaard uurtarief.
        if ($validated['contract_form'] === Project::FORM_REGIE
            && blank($validated['regie_hourly_rate']) && blank($validated['regie_day_rate'])) {
            $validated['regie_hourly_rate'] = Setting::number('calculatie.uurtarief');
        }

        $old = $project->only(array_keys($validated));
        $project->update($validated);

        AuditLog::record($project, 'contractvorm bijgewerkt', $old, $validated);

        return back()->with('success', 'Contractvorm bijgewerkt.');
    }

    public function storeMaterial(Request $request, Project $project): RedirectResponse
    {
        foreach (['quantity', 'price'] as $field) {
            $request->merge([$field => BudgetLine::parseNumber($request->input($field))]);
        }

        $validated = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit' => ['nullable', 'string', 'max:20'],
            'price' => ['required', 'numeric', 'min:0'],
        ]);

        $project->regieMaterials()->create([...$validated, 'unit' => $validated['unit'] ?? 'st']);

        return back()->with('success', 'Materiaal toegevoegd.');
    }

    public function destroyMaterial(RegieMaterial $material): RedirectResponse
    {
        if ($material->isInvoiced()) {
            return back()->with('error', 'Dit materiaal staat al op een factuur.');
        }

        $material->delete();

        return back()->with('success', 'Materiaal verwijderd.');
    }

    /**
     * Aftekenen legt vast hoeveel uur er op dit moment staat. Dat doet de
     * directie of de projectleider, niet degene die de uren boekte.
     */
    public function sign(Request $request, Project $project): RedirectResponse
    {
        $role = $request->user()->role;

        abort_unless($role->isManagement() || $role === UserRole::Projectleider, 403);

        $statement = RegieStatement::for($project);

        if ($statement->hours() <= 0) {
            return back()->with('error', 'Er staan nog geen uren op deze urenstaat.');
        }

        $project->forceFill([
            'hours_signed_by' => $request->user()->id,
            'hours_signed_at' => now(),
            'hours_signed_total' => $statement->hours(),
        ])->save();

        AuditLog::record($project, 'urenstaat afgetekend', [], ['uren' => $statement->hours()]);

        return back()->with('success', 'Afgetekend op '.rtrim(rtrim(number_format($statement->hours(), 2, ',', '.'), '0'), ',').' uur.');
    }

    /**
     * Regie factureren als concept: de uren en het materiaal die nog niet op
     * een factuur staan. Kan alleen op een afgetekende urenstaat.
     */
    public function invoice(Request $request, Project $project): RedirectResponse
    {
        $project->load('customer');
        $statement = RegieStatement::for($project);

        if (! $statement->canInvoice()) {
            return back()->with('error', $statement->blocker() ?? 'Deze urenstaat kan nu niet gefactureerd worden.');
        }

        $invoice = DB::transaction(function () use ($project, $statement) {
            $openHours = $statement->openHours();
            $materials = $project->regieMaterials()->whereNull('invoice_id')->get();

            $invoice = Invoice::create([
                'number' => Invoice::nextNumber(),
                'project_id' => $project->id,
                'customer_id' => $project->customer_id,
                'type' => InvoiceType::Regie,
                'description' => 'Regie '.$project->name,
                'date' => today(),
                'due_date' => Invoice::dueDateFor($project->customer),
                'amount' => $statement->toInvoice(),
                'vat_pct' => Setting::number('calculatie.btw'),
                'status' => InvoiceStatus::Concept,
            ]);

            $position = 0;

            if ($openHours > 0) {
                $invoice->lines()->create([
                    'description' => $statement->perDay()
                        ? 'Arbeid in regie, dagen van acht uur'
                        : 'Arbeid in regie',
                    'quantity' => $statement->units($openHours),
                    'unit' => $statement->perDay() ? 'dag' : 'uur',
                    'unit_price' => $statement->rate(),
                    'position' => $position++,
                ]);
            }

            foreach ($materials as $material) {
                $invoice->lines()->create([
                    'description' => $material->description,
                    'quantity' => $material->quantity,
                    'unit' => $material->unit,
                    'unit_price' => $material->price,
                    'position' => $position++,
                ]);
            }

            // Wat op deze factuur staat, rekenen we niet nog eens af.
            $project->timeEntries()->whereNull('invoice_id')->update(['invoice_id' => $invoice->id]);
            $project->regieMaterials()->whereNull('invoice_id')->update(['invoice_id' => $invoice->id]);

            return $invoice;
        });

        AuditLog::record($invoice, 'regiefactuur aangemaakt', [], [
            'uren' => $statement->openHours(),
            'bedrag' => (float) $invoice->amount,
        ]);

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', "Regiefactuur {$invoice->number} staat klaar als concept.");
    }
}
