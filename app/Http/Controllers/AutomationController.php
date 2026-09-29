<?php

namespace App\Http\Controllers;

use App\Automations\AutomationRegistry;
use App\Enums\AutomationMode;
use App\Models\AuditLog;
use App\Models\AutomationRun;
use App\Models\AutomationSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
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
            'mode' => $automation->mode(),
            'runs' => (int) ($stats[$automation->key()]->runs ?? 0),
            'last_run_at' => isset($stats[$automation->key()]->last_run_at)
                ? Carbon::parse($stats[$automation->key()]->last_run_at)
                : null,
        ]);

        return view('automations.index', ['automations' => $automations]);
    }

    /**
     * Modus per regel instellen (briefing §12): alleen voor admins (§15).
     */
    public function update(Request $request, string $key): RedirectResponse
    {
        abort_unless($request->user()->can('manage-team'), 403);

        $automation = collect(AutomationRegistry::all())->first(fn ($item) => $item->key() === $key);
        abort_if($automation === null, 404);

        $validated = $request->validate([
            'mode' => ['required', Rule::enum(AutomationMode::class)],
        ]);

        $old = $automation->mode();
        $setting = AutomationSetting::updateOrCreate(['key' => $key], ['mode' => $validated['mode']]);

        AuditLog::record($setting, 'modus_gewijzigd', ['modus' => $old->value], [
            'regel' => $automation->name(),
            'modus' => $validated['mode'],
        ]);

        return back()->with('success', '"'.$automation->name().'" staat nu op: '.AutomationMode::from($validated['mode'])->label().'.');
    }
}
