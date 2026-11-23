<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Os\Ai\Actions;
use App\Os\Ai\Agents;
use App\Os\Ai\StateReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Aanvragen van de Renovion-website. Zelfde adres, velden en token als in
 * de vorige app (POST /api/aanvragen met de header X-Intake-Token), zodat
 * het formulier op de website niet hoeft te veranderen. Een aanvraag wordt
 * een klant (of een bestaande klant met hetzelfde mailadres) met een nieuw
 * dossier in fase Aanvraag, plus een taak om contact op te nemen.
 */
class WebsiteLeadController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $token = (string) config('services.renovion.lead_intake_token');
        abort_if($token === '' || ! hash_equals($token, (string) $request->header('X-Intake-Token')), 401, 'Ongeldig intake-token.');

        $v = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:100'],
            'service' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:5000'],
            'desired_start' => ['nullable', 'string', 'max:100'],
        ]);

        // Dubbel verstuurd (dubbelklik, verversen): binnen tien minuten geen tweede dossier.
        $fingerprint = 'website-aanvraag:'.sha1(strtolower((string) ($v['email'] ?? $v['name'])).'|'.($v['service'] ?? '').'|'.($v['message'] ?? ''));
        if ($existing = Cache::get($fingerprint)) {
            return response()->json(['id' => $existing, 'status' => 'nieuw'], 201);
        }

        $system = Agents::systemUser();
        $state = new StateReader($system);
        $client = null;
        if (filled($v['email'] ?? null)) {
            foreach ($state->list('clients') as $c) {
                if (strtolower((string) ($c['mail'] ?? '')) === strtolower($v['email'])) {
                    $client = $c;
                }
            }
        }

        $actions = new Actions($state, now()->toDateString());
        $actions->execute($system, [[
            'type' => 'aanvraag',
            'naam' => $client['naam'] ?? $v['name'],
            'klant_id' => $client['id'] ?? null,
            'contactpersoon' => $v['name'],
            'telefoon' => $v['phone'] ?? '',
            'mailadres' => $v['email'] ?? '',
            'adres' => trim(($v['address'] ?? '').' '.($v['postal_code'] ?? '')),
            'plaats' => $v['city'] ?? '',
            'soort_werk' => $v['service'] ?? 'Renovatie',
            'tekst' => $v['message'] ?? '',
            'gewenste_start' => $v['desired_start'] ?? null,
            'bron' => 'Website',
            'activiteit' => 'Websiteaanvraag: '.($v['service'] ?? 'algemeen'),
        ]], null);

        Cache::put($fingerprint, $actions->lastProjectId, now()->addMinutes(10));

        return response()->json(['id' => $actions->lastProjectId, 'status' => 'nieuw'], 201);
    }
}
