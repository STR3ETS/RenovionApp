<?php

namespace App\Http\Controllers;

use App\Automations\AutomationRegistry;
use App\Services\OnboardingGuide;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Aan de slag": hoe het systeem werkt, wat er moet staan om te beginnen en
 * waar je alles vindt. Voor het hele team; de checklist alleen voor wie het
 * CRM beheert (uitvoerders krijgen de uitleg die bij hun werk past).
 */
class OnboardingController extends Controller
{
    public function __construct(private OnboardingGuide $guide) {}

    public function show(Request $request): View
    {
        $user = $request->user();
        $checklist = $user->can('manage-crm');

        return view('onboarding.show', [
            'checklist' => $checklist,
            'items' => $checklist ? $this->guide->items($user) : collect(),
            'progress' => $checklist ? $this->guide->progress($user) : null,
            'dismissed' => $this->guide->isDismissedFor($user),
            'automations' => config('renovion.modules.automations') && $checklist
                ? collect(AutomationRegistry::all())->map(fn ($automation) => ['name' => $automation->name(), 'description' => $automation->description()])
                : collect(),
        ]);
    }

    public function dismiss(Request $request): RedirectResponse
    {
        $this->guide->dismiss($request->user());

        return redirect()->route('dashboard')->with('success', 'Aan de slag staat niet meer in het menu. Je vindt het terug via het sterretje naast je naam.');
    }

    public function restore(Request $request): RedirectResponse
    {
        $this->guide->restore($request->user());

        return redirect()->route('onboarding')->with('success', 'Aan de slag staat weer in het menu.');
    }
}
