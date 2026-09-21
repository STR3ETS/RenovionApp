<?php

namespace App\Http\Controllers;

use App\Automations\AutomationRegistry;
use App\Models\AutomationRun;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AutomationController extends Controller
{
    public function index(): View
    {
        $stats = AutomationRun::query()
            ->selectRaw('automation, count(*) as runs, max(created_at) as last_run_at')
            ->groupBy('automation')
            ->get()
            ->keyBy('automation');

        $automations = collect(AutomationRegistry::all())->map(fn ($automation) => [
            'key' => $automation->key(),
            'name' => $automation->name(),
            'description' => $automation->description(),
            'runs' => (int) ($stats[$automation->key()]->runs ?? 0),
            'last_run_at' => isset($stats[$automation->key()]->last_run_at)
                ? Carbon::parse($stats[$automation->key()]->last_run_at)
                : null,
        ]);

        return view('automations.index', ['automations' => $automations]);
    }
}
