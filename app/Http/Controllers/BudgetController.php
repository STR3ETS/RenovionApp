<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Budget;
use App\Models\Lead;
use App\Models\Project;
use App\Support\BudgetChapterLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Begrotingen met hoofdstukken. De lijst toont wat er loopt, het detailscherm is
 * de calculatietafel: hoofdstukken met paragrafen en regels, de staart eronder
 * en opties en termijnen ernaast.
 */
class BudgetController extends Controller
{
    public function index(Request $request): View
    {
        $query = Budget::with(['project', 'customer', 'lead'])
            ->withCount('chapters')
            ->latest('updated_at');

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($zoek = $request->string('q')->toString()) {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$zoek}%")
                ->orWhere('number', 'like', "%{$zoek}%"));
        }

        $budgets = $query->paginate(25)->withQueryString();

        return view('budgets.index', [
            'budgets' => $budgets,
            // Eén doorrekening per begroting, zodat de lijst totalen kan tonen.
            'totals' => $budgets->mapWithKeys(fn (Budget $budget) => [$budget->id => $budget->totals()]),
            'status' => $request->string('status')->toString(),
            'zoek' => $zoek,
            'counts' => Budget::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function create(Request $request): View
    {
        return view('budgets.create', [
            'projects' => Project::with('customer')->orderBy('name')->get(),
            'leads' => Lead::open()->with('customer')->orderByDesc('created_at')->get(),
            'templates' => BudgetChapterLibrary::templateNames(),
            'projectId' => $request->integer('project'),
            'leadId' => $request->integer('aanvraag'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'lead_id' => ['nullable', 'exists:leads,id'],
            'template' => ['nullable', 'string', 'in:'.implode(',', BudgetChapterLibrary::templateNames())],
            'hourly_rate' => ['nullable', 'numeric', 'min:1', 'max:500'],
            'vat_pct' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'floor_area' => ['nullable', 'numeric', 'min:0'],
            'location' => ['nullable', 'string', 'max:160'],
        ]);

        $project = isset($validated['project_id']) ? Project::find($validated['project_id']) : null;
        $lead = isset($validated['lead_id']) ? Lead::find($validated['lead_id']) : null;

        $budget = DB::transaction(function () use ($validated, $project, $lead, $request) {
            $budget = Budget::create([
                'number' => Budget::nextNumber(),
                'name' => $validated['name'],
                'project_id' => $project?->id,
                'lead_id' => $lead?->id,
                'customer_id' => $project?->customer_id ?? $lead?->customer_id,
                'status' => Budget::STATUS_DRAFT,
                'hourly_rate' => $validated['hourly_rate'] ?? 55,
                'vat_pct' => $validated['vat_pct'] ?? 21,
                'floor_area' => $validated['floor_area'] ?? null,
                'location' => $validated['location'] ?? collect([$project?->address, $project?->city])->filter()->join(', ') ?: null,
                'author' => $request->user()->name,
                'dated_on' => today(),
                'created_by' => $request->user()->id,
            ]);

            // De staart waarmee Renovion standaard rekent.
            foreach (Budget::DEFAULT_MARKUPS as $position => $markup) {
                $budget->markups()->create([...$markup, 'position' => $position]);
            }

            // En het betaalschema, zodat de termijnen al op honderd procent staan.
            foreach (BudgetChapterLibrary::defaultTerms() as $position => $term) {
                $budget->terms()->create([...$term, 'position' => $position]);
            }

            $skeleton = isset($validated['template'])
                ? BudgetChapterLibrary::template($validated['template'])
                : BudgetChapterLibrary::for($project?->type, $validated['name']);

            $position = 0;

            foreach ($skeleton as $code => $paragraphs) {
                $chapter = $budget->chapters()->create([
                    'code' => $code,
                    'name' => BudgetChapterLibrary::CHAPTERS[$code] ?? $code,
                    'position' => $position++,
                ]);

                foreach ($paragraphs as $index => $name) {
                    $chapter->paragraphs()->create([
                        'code' => sprintf('.%02d', $index + 1),
                        'name' => $name,
                        'position' => $index,
                    ]);
                }
            }

            return $budget;
        });

        AuditLog::record($budget, 'begroting aangemaakt', [], [
            'nummer' => $budget->number,
            'hoofdstukken' => $budget->chapters()->count(),
        ]);

        return redirect()
            ->route('budgets.show', $budget)
            ->with('success', "Begroting {$budget->number} aangemaakt met {$budget->chapters()->count()} hoofdstukken.");
    }

    public function show(Budget $budget): View
    {
        $budget->load([
            'chapters.paragraphs.lines.priceItem',
            'markups', 'options', 'terms',
            'project.customer', 'lead.customer', 'customer',
        ]);

        return view('budgets.show', [
            'budget' => $budget,
            'totals' => $budget->totals(),
            'chapterLibrary' => BudgetChapterLibrary::CHAPTERS,
        ]);
    }

    public function update(Request $request, Budget $budget): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'hourly_rate' => ['sometimes', 'numeric', 'min:1', 'max:500'],
            'vat_pct' => ['sometimes', 'numeric', 'min:0', 'max:50'],
            'floor_area' => ['nullable', 'numeric', 'min:0'],
            'location' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:5000'],
            // De scopelijsten komen als tekst binnen, een regel per punt.
            'scope_docs' => ['nullable', 'string', 'max:5000'],
            'scope_incl' => ['nullable', 'string', 'max:5000'],
            'scope_excl' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->assertDraft($budget);

        $scope = $budget->scope ?? [];

        foreach (['docs', 'incl', 'excl'] as $key) {
            if ($request->has("scope_{$key}")) {
                $scope[$key] = collect(preg_split('/\r?\n/', (string) $request->input("scope_{$key}")))
                    ->map(fn (string $line) => trim($line))
                    ->filter()
                    ->values()
                    ->all();
            }
        }

        $fields = collect($validated)->except(['scope_docs', 'scope_incl', 'scope_excl'])->all();
        $old = $budget->only(array_keys($fields));

        $budget->update([...$fields, 'scope' => $scope]);

        AuditLog::record($budget, 'begroting bijgewerkt', $old, $fields);

        return back()->with('success', 'Begroting bijgewerkt.');
    }

    /**
     * Definitief maken zet de regels op slot. Vanaf dat moment verwijzen de
     * offerte en de nacalculatie naar hetzelfde document.
     */
    public function finalise(Request $request, Budget $budget): RedirectResponse
    {
        $totals = $budget->totals();

        if ($totals->lineCount === 0) {
            return back()->with('error', 'Een begroting zonder regels kan niet definitief worden.');
        }

        if ($totals->incompleteLines > 0) {
            return back()->with('error', sprintf(
                '%d %s nog een hoeveelheid zonder prijs. Vul die eerst in.',
                $totals->incompleteLines,
                $totals->incompleteLines === 1 ? 'regel heeft' : 'regels hebben',
            ));
        }

        $budget->update([
            'status' => Budget::STATUS_FINAL,
            'finalised_at' => now(),
            'finalised_by' => $request->user()->id,
        ]);

        AuditLog::record($budget, 'begroting definitief', ['status' => Budget::STATUS_DRAFT], [
            'status' => Budget::STATUS_FINAL,
            'totaal_excl_btw' => $totals->totalExcludingVat,
            'marge' => $totals->margin,
        ]);

        return back()->with('success', 'Begroting staat op definitief. De regels zijn nu op slot.');
    }

    /**
     * Terug naar concept: dat kan, maar het wordt vastgelegd. Een gewijzigde
     * begroting na een verstuurde offerte vraagt om een nieuwe offerteversie.
     */
    public function reopen(Request $request, Budget $budget): RedirectResponse
    {
        $budget->update([
            'status' => Budget::STATUS_DRAFT,
            'version' => $budget->version + 1,
            'finalised_at' => null,
            'finalised_by' => null,
        ]);

        AuditLog::record($budget, 'begroting heropend', ['status' => Budget::STATUS_FINAL], [
            'status' => Budget::STATUS_DRAFT,
            'versie' => $budget->version,
        ]);

        return back()->with('success', "Begroting heropend als versie {$budget->version}.");
    }

    public function destroy(Budget $budget): RedirectResponse
    {
        $this->assertDraft($budget);

        $number = $budget->number;
        $budget->delete();

        return redirect()->route('budgets.index')->with('success', "Begroting {$number} verwijderd.");
    }

    /** Een definitieve begroting staat op slot; eerst heropenen. */
    private function assertDraft(Budget $budget): void
    {
        abort_if(! $budget->isDraft(), 422, 'Deze begroting is definitief. Heropen hem eerst.');
    }
}
