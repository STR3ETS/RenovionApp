<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\MarketingInsights;
use App\Services\ReportBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Rapportages (reports/index) en marketing (reports/marketing) in de app. Dezelfde momentopnames als de
 * website (ReportBuilder, MarketingInsights); de grafiek bouwt de app zelf als balken.
 */
class ReportController extends Controller
{
    /** @var array<string, string> Dezelfde periodes als de website. */
    private const PERIODS = [
        'jaar' => 'Dit jaar',
        'kwartaal' => 'Dit kwartaal',
        '90' => 'Laatste 90 dagen',
        'alles' => 'Alles',
    ];

    public function index(Request $request, ReportBuilder $builder): JsonResponse
    {
        $type = $request->string('type', 'week')->toString();
        $type = array_key_exists($type, ReportBuilder::TYPES) ? $type : 'week';
        $project = $request->integer('project') ? Project::find($request->integer('project')) : null;

        return response()->json([
            'type' => $type,
            'types' => collect(ReportBuilder::TYPES)->map(fn (array $meta, string $key) => ['value' => $key, ...$meta])->values(),
            'projects' => $type === 'project'
                ? Project::with('customer')->orderBy('name')->get()->map(fn (Project $p) => [
                    'id' => $p->id,
                    'label' => $p->name.($p->customer ? ', '.$p->customer->name : ''),
                ])
                : [],
            'project' => $project?->id,
            'report' => $builder->build($type, $project),
            'made' => 'Opgemaakt op '.now()->translatedFormat('j F Y H:i').' door '.$request->user()->name.'.',
        ]);
    }

    public function marketing(Request $request, MarketingInsights $insights): JsonResponse
    {
        $period = $request->string('periode', 'jaar')->toString();
        $d = $insights->build($period);
        $num = fn (float $value) => rtrim(rtrim(number_format($value, 1, ',', '.'), '0'), ',');
        $rows = fn ($set) => $set->take(10)->map(fn (array $row) => [
            'label' => $row['label'],
            'total' => $row['total'],
            'won' => $row['won'],
            'conversion' => $num($row['conversion']).'%',
            'tone' => $row['conversion'] >= 40 ? 'green' : ($row['conversion'] >= 20 ? 'amber' : 'gray'),
            'value' => $row['value'],
        ])->values();

        return response()->json([
            // Een onbekende periode rekent de website als "dit jaar"; de keuzelijst toont dan wat er gevraagd is.
            'period' => $d['period'],
            'periods' => collect(self::PERIODS)->map(fn (string $label, string $value) => ['value' => (string) $value, 'label' => $label])->values(),
            'kpis' => [
                'won_value' => $d['won_value'],
                'total' => $d['total'],
                'pipeline_value' => $d['pipeline_value'],
                'repeat_customers' => $d['repeat_customers'],
            ],
            'best_source' => $d['best_source'] ? [
                'label' => $d['best_source']['label'],
                'conversion' => $num($d['best_source']['conversion']),
                'average' => $d['best_source']['average'],
            ] : null,
            'per_month' => collect($d['per_month'])->map(fn (int $count, string $month) => [
                'month' => $month,
                'label' => Carbon::createFromFormat('Y-m', $month)->translatedFormat('M'),
                'count' => $count,
            ])->values(),
            'breakdowns' => [
                ['key' => 'sources', 'title' => 'Per bron', 'about' => 'De campagne waar de aanvraag aan hing', 'rows' => $rows($d['sources'])],
                ['key' => 'channels', 'title' => 'Per kanaal', 'about' => 'Hoe de aanvraag binnenkwam', 'rows' => $rows($d['channels'])],
                ['key' => 'services', 'title' => 'Per dienst', 'about' => 'Welk werk er gevraagd wordt', 'rows' => $rows($d['services'])],
            ],
        ]);
    }
}
