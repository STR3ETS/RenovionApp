<?php

namespace App\Http\Controllers\Os;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Os\Ai\Claude;
use App\Os\People;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * De schil van Renovion OS: één pagina, de app regelt de schermen zelf.
 */
class ShellController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        return view('os', [
            'boot' => [
                'user' => [
                    'id' => $user->id,
                    'naam' => $user->name,
                    'mail' => $user->email,
                    'rol' => $user->role->label(),
                    'person' => $user->person_id,
                ],
                'today' => now()->toDateString(),
                'debug' => app()->isLocal(),
                'ai' => Claude::enabled(),
                'csrf' => csrf_token(),
                'urls' => [
                    'state' => route('os.state'),
                    'sync' => route('os.sync'),
                    'pull' => route('os.pull'),
                    'lease' => route('os.lease'),
                    'files' => route('os.files.store'),
                    'logout' => route('logout'),
                    'password' => route('os.password', ['person' => '__ID__']),
                    'tool' => asset('os/begtool.html'),
                    'nova' => route('os.nova'),
                    'novaConfirm' => route('os.nova.confirm', ['proposal' => '__ID__']),
                    'agentDecide' => route('os.agents.decide', ['approval' => '__ID__']),
                    'agentRound' => route('os.agents.round'),
                    'workOrderRead' => route('os.workorder.read'),
                    'offerWrite' => route('os.offer.write'),
                ],
            ],
        ]);
    }

    /** Het app-manifest, zodat Renovion OS op het beginscherm kan. */
    public function manifest(): Response
    {
        $manifest = [
            'name' => 'Renovion OS',
            'short_name' => 'Renovion',
            'description' => 'Eén platform voor het hele bouwproject.',
            'lang' => 'nl',
            'start_url' => '/vandaag',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => '#0D1A2E',
            'theme_color' => '#0D1A2E',
            'icons' => [
                ['src' => '/os/img/icon-i192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => '/os/img/icon-i512.png', 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => '/os/img/icon-m512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ];

        return response(json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 200, ['Content-Type' => 'application/manifest+json']);
    }

    /**
     * Een nieuw wachtwoord voor iemand uit het team (alleen directie). Er is
     * geen mailserver, dus het wachtwoord komt één keer terug in beeld.
     */
    public function password(Request $request, string $person): JsonResponse
    {
        abort_unless($request->user()->role->isManagement(), 403);

        $account = User::query()->where('person_id', $person)->first();
        if (! $account) {
            return response()->json(['message' => 'Deze persoon heeft nog geen account. Vul een e-mailadres in en sla op; dan maakt het systeem er een.'], 404);
        }

        $password = People::temporaryPassword($account);

        return response()->json(['naam' => $account->name, 'mail' => $account->email, 'wachtwoord' => $password]);
    }
}
