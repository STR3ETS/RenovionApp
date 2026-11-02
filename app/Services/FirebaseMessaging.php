<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Pushmeldingen via Firebase Cloud Messaging (HTTP v1 API). Werkt voor Android en, zodra de
 * APNs-sleutel in Firebase staat, ook voor iOS. Geen SDK nodig: een met de serviceaccount-sleutel
 * ondertekende JWT levert een toegangstoken op (50 minuten gecachet).
 */
class FirebaseMessaging
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function isConfigured(): bool
    {
        $path = $this->credentialsPath();

        return $path !== null && is_readable($path);
    }

    /**
     * @param  array<string, string>  $data
     * @return bool false als het token niet meer geldig is (app verwijderd, opnieuw geïnstalleerd)
     */
    public function send(string $token, string $title, string $body, array $data = []): bool
    {
        $credentials = $this->credentials();

        $response = Http::withToken($this->accessToken())
            ->timeout(5)
            ->post("https://fcm.googleapis.com/v1/projects/{$credentials['project_id']}/messages:send", [
                'message' => [
                    'token' => $token,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data' => $data,
                    'android' => [
                        'priority' => 'HIGH',
                        'notification' => [
                            'channel_id' => 'meldingen',
                            'icon' => 'ic_stat_renovion',
                            'color' => '#F85B0B',
                        ],
                    ],
                    'apns' => ['payload' => ['aps' => ['sound' => 'default']]],
                ],
            ]);

        if ($response->successful()) {
            return true;
        }

        // Token hoort niet meer bij een actieve app-installatie, of bij een ander Firebase-project
        // (bijv. na de overstap van het testproject naar productie): dan weg ermee.
        $status = $response->json('error.status');
        $errorCodes = collect($response->json('error.details', []))->pluck('errorCode')->filter();
        if ($response->status() === 404
            || $status === 'UNREGISTERED'
            || $errorCodes->intersect(['UNREGISTERED', 'SENDER_ID_MISMATCH'])->isNotEmpty()
            || ($status === 'INVALID_ARGUMENT' && str_contains((string) $response->json('error.message'), 'token'))) {
            return false;
        }

        Log::warning('Pushmelding mislukt', ['status' => $response->status(), 'error' => $response->json('error')]);

        return true;
    }

    private function accessToken(): string
    {
        return Cache::remember('firebase-access-token', now()->addMinutes(50), function () {
            $credentials = $this->credentials();
            $now = time();

            $encode = fn (array $part) => rtrim(strtr(base64_encode(json_encode($part)), '+/', '-_'), '=');
            $unsigned = $encode(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$encode([
                'iss' => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => $credentials['token_uri'],
                'iat' => $now,
                'exp' => $now + 3600,
            ]);

            if (! openssl_sign($unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Firebase-sleutel kon niet ondertekenen.');
            }

            $jwt = $unsigned.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

            return Http::asForm()->timeout(5)->post($credentials['token_uri'], [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ])->throw()->json('access_token');
        });
    }

    /**
     * @return array{project_id: string, client_email: string, private_key: string, token_uri: string}
     */
    private function credentials(): array
    {
        return once(fn () => json_decode((string) file_get_contents((string) $this->credentialsPath()), true, flags: JSON_THROW_ON_ERROR));
    }

    private function credentialsPath(): ?string
    {
        $path = config('services.firebase.credentials');

        if (! $path) {
            return null;
        }

        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) ? $path : base_path($path);
    }
}
