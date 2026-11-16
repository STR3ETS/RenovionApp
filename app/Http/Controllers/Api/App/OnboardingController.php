<?php

namespace App\Http\Controllers\Api\App;

use App\Automations\AutomationRegistry;
use App\Http\Controllers\Controller;
use App\Services\OnboardingGuide;
use App\Support\App\AppColours;
use App\Support\App\OnboardingContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * "Aan de slag" in de app (onboarding/show): de uitleg per rol, en voor kantoor de live checklist uit
 * OnboardingGuide met de automations. Verbergen en weer tonen gaan via de OnboardingController van de website.
 */
class OnboardingController extends Controller
{
    public function show(Request $request, OnboardingGuide $guide): JsonResponse
    {
        $user = $request->user();
        $crm = $user->can('manage-crm');
        $progress = $crm ? $guide->progress($user) : null;
        $automations = config('renovion.modules.automations') && $crm;

        return response()->json([
            'crm' => $crm,
            'dismissed' => $guide->isDismissedFor($user),
            'hero' => OnboardingContent::hero($crm),
            'rhythm' => $crm ? [] : OnboardingContent::RHYTHM,
            'progress' => $progress ? [
                ...$progress,
                'percentage' => $progress['total'] > 0 ? (int) round($progress['done'] / $progress['total'] * 100) : 0,
            ] : null,
            'items' => $crm ? $guide->items($user)->map(fn (array $item) => [
                'key' => $item['key'],
                'title' => $item['title'],
                'description' => $item['description'],
                'done' => $item['done'],
                'optional' => $item['optional'],
                'note' => $item['note'],
                'action' => $item['url'] ? $item['action'] : null,
                'path' => AppColours::path($item['url']),
            ])->values() : [],
            'flow' => OnboardingContent::flow($crm),
            'modules' => OnboardingContent::modules($user, $crm),
            'nova' => $crm ? OnboardingContent::nova() : [],
            'automations' => $automations
                ? collect(AutomationRegistry::all())->map(fn ($automation) => ['name' => $automation->name(), 'description' => $automation->description()])->values()
                : [],
            'automations_path' => $automations && Route::has('automations.index') ? route('automations.index', [], false) : null,
            'tips' => OnboardingContent::tips($crm),
        ]);
    }
}
