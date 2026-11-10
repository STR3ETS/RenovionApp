<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\CostReviewService;
use App\Services\FinanceOverview;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Het financiële beeld van het bedrijf en de nacalculatie per project.
 */
class FinanceController extends Controller
{
    public function index(FinanceOverview $overview): View
    {
        return view('finance.index', ['finance' => $overview->build()]);
    }

    /**
     * Nacalculatie: per project begroot tegenover werkelijk en verwacht, plus de
     * leerpunten over projecten heen.
     */
    public function costReviews(Request $request, CostReviewService $service): View
    {
        $projects = Project::active()
            ->with('customer')
            ->orderBy('name')
            ->get()
            ->map(fn (Project $project) => ['project' => $project, 'review' => $service->for($project)])
            ->filter(fn (array $row) => $row['review'] !== null)
            ->values();

        return view('finance.cost-reviews', [
            'rows' => $projects,
            'lessons' => $service->lessons(),
        ]);
    }
}
