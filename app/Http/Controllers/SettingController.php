<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\AutomationRun;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Instellingen: bedrijfsgegevens en de standaardwaarden waarmee het systeem
 * rekent. Geheimen staan hier bewust niet in; die horen in .env en worden hier
 * alleen als "ingesteld" of "ontbreekt" gemeld.
 */
class SettingController extends Controller
{
    public function index(): View
    {
        return view('settings.index', [
            'fields' => Setting::FIELDS,
            'values' => Setting::allValues(),
            'groups' => Setting::groups(),
            'environment' => $this->environment(),
            'modules' => [
                'Offertes' => (bool) config('renovion.modules.quotes'),
                'Automations' => (bool) config('renovion.modules.automations'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable', 'string', 'max:500'],
        ]);

        $changed = [];

        foreach ($validated['settings'] as $key => $value) {
            if (! array_key_exists($key, Setting::FIELDS)) {
                continue;
            }

            $current = Setting::get($key);

            if ((string) $value === $current) {
                continue;
            }

            Setting::put($key, $value, $request->user()->id);
            $changed[$key] = $value;

            // Per instelling vastleggen wat er stond en wat er nu staat: deze
            // getallen bepalen wat klanten betalen, dus die historie wil je.
            AuditLog::record(
                Setting::firstWhere('key', $key),
                'instelling gewijzigd',
                [$key => $current],
                [$key => $value],
            );
        }

        return back()->with('success', $changed === []
            ? 'Niets gewijzigd.'
            : count($changed).' '.(count($changed) === 1 ? 'instelling' : 'instellingen').' opgeslagen.');
    }

    /**
     * De staat van wat in .env hoort te staan. Nooit de waarde zelf: alleen of
     * het er is, zodat niemand een sleutel van het scherm kan overtypen.
     *
     * @return list<array{label: string, ready: bool, about: string}>
     */
    private function environment(): array
    {
        return [
            [
                'label' => 'AI-sleutel voor Nova',
                'ready' => filled(config('renovion.ai.api_key')),
                'about' => 'Zonder sleutel werkt Nova niet: geen voorstellen, geen dagbriefing.',
            ],
            [
                'label' => 'Token voor de aanvraag-API',
                'ready' => filled(config('renovion.lead_intake_token')),
                'about' => 'Nodig om aanvragen van de website binnen te laten komen.',
            ],
            [
                'label' => 'Mailserver',
                'ready' => config('mail.default') !== 'log' && filled(config('mail.mailers.smtp.host')),
                'about' => 'Zonder mailserver verstuurt het systeem niets, maar legt het wel vast.',
            ],
            [
                'label' => 'Scheduler',
                'ready' => AutomationRun::query()->where('created_at', '>=', now()->subDay())->exists(),
                'about' => 'Automations draaien elk kwartier. Zonder cron gebeurt er niets.',
            ],
        ];
    }
}
