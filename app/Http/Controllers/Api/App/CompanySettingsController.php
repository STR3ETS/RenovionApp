<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SettingController;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

/**
 * Organisatie-instellingen in de app (settings/index, niet de meldingen: dat is api/app/instellingen):
 * bedrijfsgegevens en standaardwaarden per groep, of de geheimen in .env er zijn, de modules en de
 * hulplinks. Opslaan gaat via SettingController@update van de website (zelfde validatie en audit).
 */
class CompanySettingsController extends Controller
{
    public function show(): JsonResponse
    {
        $values = Setting::allValues();

        return response()->json([
            'groups' => array_map(fn (string $group) => [
                'name' => $group,
                'fields' => collect(Setting::FIELDS)
                    ->filter(fn (array $field) => $field['group'] === $group)
                    ->map(fn (array $field, string $key) => [
                        'key' => $key,
                        'label' => $field['label'],
                        'hint' => $field['hint'] !== '' ? $field['hint'] : null,
                        'type' => $field['type'],
                        'value' => $values[$key],
                    ])->values(),
            ], Setting::groups()),
            'environment' => SettingController::environment(),
            'modules' => collect(SettingController::modules())->map(fn (bool $enabled, string $name) => ['name' => $name, 'enabled' => $enabled])->values(),
            'links' => array_values(array_filter([
                Route::has('onboarding') ? ['label' => 'Aan de slag', 'icon' => 'rocket-launch', 'path' => route('onboarding', [], false)] : null,
                Route::has('docs.api') ? ['label' => 'API voor aanvragen', 'icon' => 'globe-alt', 'path' => route('docs.api', [], false)] : null,
                Route::has('styleguide') ? ['label' => 'Stijlgids', 'icon' => 'light-bulb', 'path' => route('styleguide', [], false)] : null,
            ])),
        ]);
    }
}
