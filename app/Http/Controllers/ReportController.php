<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\MarketingInsights;
use App\Services\ReportBuilder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Rapportages en marketing. Een rapport is geen opgeslagen bestand maar een
 * momentopname die je op elk moment opnieuw kunt maken uit de data die er is.
 */
class ReportController extends Controller
{
    public function index(Request $request, ReportBuilder $builder): View
    {
        $type = $request->string('type', 'week')->toString();
        $type = array_key_exists($type, ReportBuilder::TYPES) ? $type : 'week';

        $project = $request->integer('project') ? Project::find($request->integer('project')) : null;

        return view('reports.index', [
            'type' => $type,
            'types' => ReportBuilder::TYPES,
            'report' => $builder->build($type, $project),
            'projects' => Project::with('customer')->orderBy('name')->get(),
            'projectFilter' => $project?->id,
        ]);
    }

    public function marketing(Request $request, MarketingInsights $insights): View
    {
        $period = $request->string('periode', 'jaar')->toString();

        return view('reports.marketing', [
            'data' => $insights->build($period),
            'periods' => [
                'jaar' => 'Dit jaar',
                'kwartaal' => 'Dit kwartaal',
                '90' => 'Laatste 90 dagen',
                'alles' => 'Alles',
            ],
        ]);
    }
}
