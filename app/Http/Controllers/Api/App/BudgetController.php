<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\BudgetController as WebBudgetController;
use App\Http\Controllers\BudgetExtrasController;
use App\Http\Controllers\BudgetLineController;
use App\Http\Controllers\BudgetStructureController;
use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\BudgetChapter;
use App\Models\BudgetLine;
use App\Models\BudgetMarkup;
use App\Models\BudgetOption;
use App\Models\BudgetParagraph;
use App\Models\BudgetTerm;
use App\Support\BudgetChapterLibrary;
use App\Support\BudgetTotals;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Begrotingen in de app (budgets/index en budgets/show): de lijst met filters en zoeken, en de calculatietafel
 * met hoofdstukken, paragrafen, regels, de staart, opties, het betaalschema en de uitgangspunten.
 *
 * Elke wijziging gaat via dezelfde controller als de website (zelfde validatie en regels, zelfde slot op een
 * definitieve begroting); de app krijgt daarna de hele begroting terug, zodat alle totalen meteen kloppen.
 */
class BudgetController extends Controller
{
    private const PER_PAGE = 25;

    /** Kostensoorten in de volgorde en kleur van de website ("Waar het geld zit"). */
    private const CATEGORIES = [
        'arbeid' => ['Arbeid', 'bg-brand-500'],
        'materiaal' => ['Materiaal', 'bg-lead-500'],
        'materieel' => ['Materieel', 'bg-calc-500'],
        'onderaanneming' => ['Onderaanneming', 'bg-offerte-500'],
        'overig' => ['Overig', 'bg-gray-400'],
    ];

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', 'in:'.Budget::STATUS_DRAFT.','.Budget::STATUS_FINAL],
            'q' => ['nullable', 'string', 'max:100'],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ]);

        $status = $request->string('status')->toString();
        $zoek = trim($request->string('q')->toString());

        $budgets = Budget::with(['project', 'customer', 'lead.customer'])
            ->withCount('chapters')
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($zoek !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$zoek}%")
                ->orWhere('number', 'like', "%{$zoek}%")))
            ->latest('updated_at')
            ->paginate(self::PER_PAGE, ['*'], 'pagina');

        $counts = Budget::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return response()->json([
            'budgets' => $budgets->getCollection()->map(fn (Budget $budget) => $this->row($budget))->values(),
            'page' => $budgets->currentPage(),
            'more' => $budgets->hasMorePages(),
            'total' => $budgets->total(),
            'counts' => [
                '' => (int) $counts->sum(),
                Budget::STATUS_DRAFT => (int) ($counts[Budget::STATUS_DRAFT] ?? 0),
                Budget::STATUS_FINAL => (int) ($counts[Budget::STATUS_FINAL] ?? 0),
            ],
            'links' => ['create' => route('budgets.create', [], false)],
        ]);
    }

    public function show(Budget $budget): JsonResponse
    {
        return response()->json($this->payload($budget));
    }

    // Kop: definitief maken en heropenen.

    public function finalise(Request $request, Budget $budget): JsonResponse
    {
        return $this->via($budget, fn () => app(WebBudgetController::class)->finalise($request, $budget));
    }

    public function reopen(Request $request, Budget $budget): JsonResponse
    {
        return $this->via($budget, fn () => app(WebBudgetController::class)->reopen($request, $budget));
    }

    /** De uitgangspunten (scope_docs, scope_incl, scope_excl) en de andere velden van de begroting. */
    public function update(Request $request, Budget $budget): JsonResponse
    {
        return $this->via($budget, fn () => app(WebBudgetController::class)->update($request, $budget));
    }

    // Indeling: hoofdstukken en paragrafen.

    public function storeChapter(Request $request, Budget $budget): JsonResponse
    {
        return $this->via($budget, fn () => app(BudgetStructureController::class)->storeChapter($request, $budget));
    }

    public function destroyChapter(BudgetChapter $chapter): JsonResponse
    {
        return $this->via($chapter->budget, fn () => app(BudgetStructureController::class)->destroyChapter($chapter));
    }

    public function storeParagraph(Request $request, BudgetChapter $chapter): JsonResponse
    {
        return $this->via($chapter->budget, fn () => app(BudgetStructureController::class)->storeParagraph($request, $chapter));
    }

    // Regels.

    public function storeLine(Request $request, BudgetParagraph $paragraph): JsonResponse
    {
        return $this->via($paragraph->chapter->budget, fn () => app(BudgetLineController::class)->store($request, $paragraph));
    }

    public function updateLine(Request $request, BudgetLine $line): JsonResponse
    {
        return $this->via($line->paragraph->chapter->budget, fn () => app(BudgetLineController::class)->update($request, $line));
    }

    public function destroyLine(BudgetLine $line): JsonResponse
    {
        return $this->via($line->paragraph->chapter->budget, fn () => app(BudgetLineController::class)->destroy($line));
    }

    // Staart, opties en termijnen.

    public function storeMarkup(Request $request, Budget $budget): JsonResponse
    {
        return $this->via($budget, fn () => app(BudgetExtrasController::class)->storeMarkup($request, $budget));
    }

    public function updateMarkup(Request $request, BudgetMarkup $markup): JsonResponse
    {
        return $this->via($markup->budget, fn () => app(BudgetExtrasController::class)->updateMarkup($request, $markup));
    }

    public function destroyMarkup(BudgetMarkup $markup): JsonResponse
    {
        return $this->via($markup->budget, fn () => app(BudgetExtrasController::class)->destroyMarkup($markup));
    }

    public function storeOption(Request $request, Budget $budget): JsonResponse
    {
        return $this->via($budget, fn () => app(BudgetExtrasController::class)->storeOption($request, $budget));
    }

    public function updateOption(Request $request, BudgetOption $option): JsonResponse
    {
        return $this->via($option->budget, fn () => app(BudgetExtrasController::class)->updateOption($request, $option));
    }

    public function destroyOption(BudgetOption $option): JsonResponse
    {
        return $this->via($option->budget, fn () => app(BudgetExtrasController::class)->destroyOption($option));
    }

    public function storeTerm(Request $request, Budget $budget): JsonResponse
    {
        return $this->via($budget, fn () => app(BudgetExtrasController::class)->storeTerm($request, $budget));
    }

    public function updateTerm(Request $request, BudgetTerm $term): JsonResponse
    {
        return $this->via($term->budget, fn () => app(BudgetExtrasController::class)->updateTerm($request, $term));
    }

    public function destroyTerm(BudgetTerm $term): JsonResponse
    {
        return $this->via($term->budget, fn () => app(BudgetExtrasController::class)->destroyTerm($term));
    }

    /**
     * De wijziging uitvoeren met de controller van de website. Validatie- en slotfouten komen als 422 terug
     * (het verzoek verwacht JSON); een eigen JSON-fout van die controller gaat ongewijzigd door. Anders de
     * verse begroting in plaats van de redirect.
     */
    private function via(Budget $budget, Closure $action): JsonResponse
    {
        $response = $action();

        if ($response instanceof JsonResponse) {
            return $response;
        }

        return response()->json($this->payload(Budget::findOrFail($budget->id)));
    }

    /** @return array<string, mixed> */
    private function row(Budget $budget): array
    {
        $totals = $budget->totals();

        return [
            'id' => $budget->id,
            'name' => $budget->name,
            'number' => $budget->number,
            'draft' => $budget->isDraft(),
            'status' => $this->status($budget),
            'incomplete' => $totals->incompleteLines,
            'sub' => implode(', ', [
                $budget->number,
                $budget->project?->name ?? $budget->lead?->customer?->name ?? $budget->customer?->name ?? 'geen dossier',
                $budget->chapters_count.' '.($budget->chapters_count === 1 ? 'hoofdstuk' : 'hoofdstukken'),
                $totals->lineCount.' '.($totals->lineCount === 1 ? 'regel' : 'regels'),
            ]),
            'total' => $totals->totalExcludingVat,
            'meta' => number_format($totals->hours, 0, ',', '.').' uur, marge '.number_format($totals->margin, 1, ',', '.').'%',
            'path' => route('budgets.show', $budget, false),
        ];
    }

    /** @return array{label: string, classes: string} */
    private function status(Budget $budget): array
    {
        return $budget->isDraft()
            ? ['label' => 'Concept', 'classes' => 'bg-gray-100 text-gray-700']
            : ['label' => 'Definitief', 'classes' => 'bg-green-100 text-green-800'];
    }

    /** @return array<string, mixed> */
    private function payload(Budget $budget): array
    {
        $budget->load([
            'chapters.paragraphs.lines.priceItem',
            'markups', 'options', 'terms',
            'project.customer', 'lead.customer', 'customer',
        ]);

        $totals = $budget->totals();
        $rate = (float) $budget->hourly_rate;
        $chapterTotals = collect($totals->chapters)->keyBy('code');
        $highest = max(1, (float) (collect($totals->chapters)->max('total') ?: 1));
        $shares = $totals->categoryShares();
        $coverage = $budget->termsCoverage();
        $customer = $budget->customer ?? $budget->project?->customer ?? $budget->lead?->customer;

        return [
            'id' => $budget->id,
            'number' => $budget->number,
            'version' => $budget->version,
            'name' => $budget->name,
            'eyebrow' => $budget->number.', versie '.$budget->version,
            'subtitle' => collect([$budget->project?->name, $budget->location, $budget->author])->filter()->join(', '),
            'draft' => $budget->isDraft(),
            'status' => $this->status($budget),
            'rate' => $rate,
            'kpis' => [
                'total_excl' => $totals->totalExcludingVat,
                'margin' => $this->number($totals->margin, 1).'%',
                'direct' => $totals->direct,
                'hours' => $this->number($totals->hours, 0).' uur',
                'markup_total' => $totals->markupTotal,
                'surcharge' => $this->number($totals->surcharge, 1).'%',
                'total_incl' => $totals->totalIncludingVat,
                'vat' => $this->number($totals->vatPercentage, 0).'% btw',
                'per_m2' => $totals->pricePerSquareMetre,
                'floor_area' => $budget->floor_area ? $this->number((float) $budget->floor_area, 0).' m2' : 'geen oppervlak',
            ],
            'line_count' => $totals->lineCount,
            'incomplete' => $totals->incompleteLines,
            'categories' => collect(self::CATEGORIES)->map(fn (array $category, string $key) => [
                'key' => $key,
                'label' => $category[0],
                'color' => $category[1],
                'amount' => $totals->categories[$key],
                'share' => $shares[$key],
            ])->values(),
            'chapters' => $budget->chapters->map(function (BudgetChapter $chapter) use ($chapterTotals, $highest, $totals, $rate) {
                $total = (float) ($chapterTotals[$chapter->code]['total'] ?? 0);

                return [
                    'id' => $chapter->id,
                    'code' => $chapter->code,
                    'name' => $chapter->name,
                    'total' => $total,
                    'bar' => (int) round($total / $highest * 100),
                    'share' => ($totals->direct > 0 ? number_format($total / $totals->direct * 100, 1, ',', '.') : '0,0').'% van de kosten',
                    'lines' => $chapter->paragraphs->sum(fn (BudgetParagraph $paragraph) => $paragraph->lines->count()),
                    'paragraphs' => $chapter->paragraphs->map(fn (BudgetParagraph $paragraph) => [
                        'id' => $paragraph->id,
                        'label' => $chapter->code.$paragraph->code.' '.$paragraph->name,
                        'total' => $paragraph->total($rate),
                        'lines' => $paragraph->lines->map(fn (BudgetLine $line) => $this->line($line, $rate))->values(),
                    ])->values(),
                ];
            })->values(),
            'chapter_library' => collect(BudgetChapterLibrary::CHAPTERS)
                ->map(fn (string $name, string|int $code) => ['code' => (string) $code, 'name' => $name])
                ->values(),
            'markups' => $budget->markups->values()->map(fn (BudgetMarkup $markup, int $index) => [
                'id' => $markup->id,
                'name' => $markup->name,
                'percentage' => $this->number((float) $markup->percentage, 3),
                'amount' => (float) ($totals->markups[$index]['amount'] ?? 0),
            ]),
            'markup_total' => $totals->markupTotal,
            'margin' => $this->number($totals->margin, 2).'%',
            'surcharge' => $this->number($totals->surcharge, 2).'%',
            'options_summary' => $totals->approvedExtras > 0
                ? '€ '.number_format($totals->approvedExtras, 0, ',', '.').' akkoord, € '.number_format($totals->openOptions, 0, ',', '.').' nog open.'
                : '€ '.number_format($totals->openOptions, 0, ',', '.').' open, nog niets akkoord.',
            'options' => $budget->options->map(fn (BudgetOption $option) => $this->option($option, $totals))->values(),
            'terms_summary' => abs($coverage - 100) < 0.01
                ? ['ok' => true, 'text' => 'De termijnen dekken de volle honderd procent.']
                : ['ok' => false, 'text' => 'De termijnen tellen op tot '.$this->number($coverage, 2).' procent. Er '
                    .($coverage < 100 ? 'ontbreekt' : 'staat te veel').' '.$this->number(abs(100 - $coverage), 2).' procent.'],
            'terms' => $budget->terms->map(fn (BudgetTerm $term) => [
                'id' => $term->id,
                'description' => $term->description,
                'percentage' => $this->number((float) $term->percentage, 2),
                'fixed' => (bool) $term->fixed,
                'amount' => $budget->termAmount($term, $totals),
                'status' => ['label' => $term->statusLabel(), 'classes' => $this->termClasses($term)],
            ])->values(),
            'contract_sum' => $totals->contractSum(),
            'scope' => $budget->scopeLists(),
            'project' => $budget->project ? ['name' => $budget->project->name, 'path' => route('projects.show', $budget->project, false)] : null,
            'links' => [
                'web' => route('budgets.show', $budget, false),
                'project' => $budget->project ? ['name' => $budget->project->name, 'path' => route('projects.show', $budget->project, false)] : null,
                'lead' => $budget->lead ? route('leads.show', $budget->lead, false) : null,
                'customer' => $customer ? ['name' => $customer->name, 'path' => route('customers.show', $customer, false)] : null,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function line(BudgetLine $line, float $rate): array
    {
        $costs = $line->costs($rate);
        $field = fn (string $name, int $decimals) => (float) $line->{$name} === 0.0 ? '' : $this->number((float) $line->{$name}, $decimals);

        return [
            'id' => $line->id,
            'text' => $line->type === BudgetLine::TYPE_TEXT,
            'description' => $line->description,
            'source' => $line->source,
            'quantity' => $this->number((float) $line->quantity, 3),
            'unit' => $line->unit,
            'norm_hours' => $field('norm_hours', 4),
            'material' => $field('material', 2),
            'equipment' => $field('equipment', 2),
            'subcontracting' => $field('subcontracting', 2),
            'other' => $field('other', 2),
            'summary' => collect([
                $costs['uren'] > 0 ? $this->number($costs['uren'], 2).' uur' : null,
                $costs['materiaal'] > 0 ? 'mat. '.number_format($costs['materiaal'], 0, ',', '.') : null,
                $costs['materieel'] > 0 ? 'mtr. '.number_format($costs['materieel'], 0, ',', '.') : null,
                $costs['onderaanneming'] > 0 ? 'ond. '.number_format($costs['onderaanneming'], 0, ',', '.') : null,
                $costs['overig'] > 0 ? 'ov. '.number_format($costs['overig'], 0, ',', '.') : null,
            ])->filter()->join(', '),
            'total' => round($costs['totaal'], 2),
            'incomplete' => $line->isIncomplete(),
        ];
    }

    /** @return array<string, mixed> */
    private function option(BudgetOption $option, BudgetTotals $totals): array
    {
        return [
            'id' => $option->id,
            'description' => $option->description,
            'note' => $option->note,
            'quantity' => $this->number((float) $option->quantity, 2).' '.$option->unit,
            'price' => (float) $option->price === 0.0 ? '' : number_format((float) $option->price, 2, ',', '.'),
            'needs_pricing' => $option->needsPricing(),
            'amount' => round((float) $option->quantity * (float) $option->price * $totals->markupFactor, 2),
            'status' => [
                'value' => $option->status,
                'label' => $option->statusLabel(),
                'classes' => match ($option->status) {
                    BudgetOption::STATUS_APPROVED => 'bg-green-100 text-green-800',
                    BudgetOption::STATUS_DECLINED => 'bg-gray-100 text-gray-600',
                    default => 'bg-calc-100 text-calc-700',
                },
            ],
            // Dezelfde knoppen als de website: de twee statussen die hij nu niet heeft.
            'actions' => collect([
                [BudgetOption::STATUS_APPROVED, 'Akkoord'],
                [BudgetOption::STATUS_OPEN, 'Open'],
                [BudgetOption::STATUS_DECLINED, 'Afwijzen'],
            ])->reject(fn (array $action) => $action[0] === $option->status)
                ->map(fn (array $action) => ['value' => $action[0], 'label' => $action[1]])
                ->values(),
        ];
    }

    private function termClasses(BudgetTerm $term): string
    {
        return match ($term->status) {
            BudgetTerm::STATUS_PAID => 'bg-green-100 text-green-800',
            BudgetTerm::STATUS_INVOICED => 'bg-lead-100 text-lead-700',
            BudgetTerm::STATUS_DUE => 'bg-amber-100 text-amber-800',
            default => 'bg-gray-100 text-gray-700',
        };
    }

    /** Getal zoals de website het toont: Nederlandse notatie zonder overbodige nullen. */
    private function number(float $value, int $decimals): string
    {
        $text = number_format($value, $decimals, ',', '.');

        return $decimals === 0 ? $text : rtrim(rtrim($text, '0'), ',');
    }
}
