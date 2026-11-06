<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\ChatChannel;
use App\Models\User;
use App\Services\AttentionService;
use App\Support\AppMenu;
use App\Support\AppNews;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Inloggen in de iOS/Android-app (mobile/app): een token per telefoon in plaats van een sessiecookie,
 * want de app draait op een eigen adres (https://localhost, capacitor://localhost) en niet op de server.
 */
class SessionController extends Controller
{
    /** Adressen waarop de app zelf draait: alleen daarheen mag een serverpagina terugverwijzen. */
    public const APP_ORIGINS = ['https://localhost', 'http://localhost', 'capacitor://localhost'];

    public function store(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'De combinatie van e-mailadres en wachtwoord klopt niet.']);
        }

        return response()->json([
            'token' => $user->createToken($credentials['device'] ?? 'Renovion OS-app')->plainTextToken,
            ...$this->profile($user),
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->profile($request->user()));
    }

    public function destroy(Request $request): Response
    {
        // Deze telefoon krijgt geen pushmeldingen meer voor deze gebruiker.
        if ($request->filled('push_token')) {
            $request->user()->deviceTokens()->where('token', $request->input('push_token'))->delete();
        }

        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    /**
     * Eenmalige inloglink voor een scherm dat nog niet in de app zit: de app opent de serverpagina
     * en die logt de gebruiker in. Een minuut geldig en maar één keer te gebruiken.
     */
    public function webCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'naar' => ['required', 'string', 'max:500', 'starts_with:/', 'not_regex:/^\/\//'],
            'terug' => ['required', Rule::in(self::APP_ORIGINS)],
        ]);

        $code = Str::random(48);
        Cache::put("app-web-code:{$code}", [
            'user' => $request->user()->id,
            'naar' => $validated['naar'],
            'terug' => $validated['terug'],
        ], now()->addMinute());

        return response()->json(['url' => route('app.web-session', ['code' => $code])]);
    }

    /** @return array<string, mixed> */
    private function profile(User $user): array
    {
        $crm = $user->can('manage-crm');

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'first_name' => Str::before($user->name, ' '),
                'initials' => Str::upper(Str::substr($user->name, 0, 1)),
                'role' => $user->role->value,
                'role_label' => $user->role->label(),
            ],
            'can' => [
                'internal' => $user->can('internal'),
                'crm' => $crm,
                'team' => $user->can('manage-team'),
                'quotes' => (bool) config('renovion.modules.quotes'),
                'automations' => (bool) config('renovion.modules.automations'),
            ],
            'menu' => $user->can('internal') ? AppMenu::forApp($user) : null,
            'news' => AppNews::ITEMS,
            'counts' => $user->can('internal') ? [
                'attention' => $crm ? AttentionService::cachedCount() : 0,
                'chat' => ChatChannel::unreadTotalFor($user),
                'notifications' => $user->unreadNotifications()->count(),
            ] : ['attention' => 0, 'chat' => 0, 'notifications' => 0],
        ];
    }
}
