<?php

namespace App\Http\Controllers;

use App\Enums\ActionSource;
use App\Models\AuditLog;
use App\Models\ChatChannel;
use App\Models\ToolOffer;
use App\Support\OfferToolAccess;
use App\Support\OfferToolPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * De offertetool voor de klant: inloggen met mobiel nummer en toegangscode,
 * de offerte bekijken en akkoord geven. Zonder geldige code komt er geen
 * offerte en geen begroting mee, ook niet in de broncode.
 *
 * De geldigheid wordt bij elk verzoek opnieuw gecontroleerd, niet alleen bij
 * het inloggen: dichtzetten of verlopen werkt dus direct.
 */
class OfferToolClientController extends Controller
{
    private const MAX_SIGNATURE_BYTES = 2 * 1024 * 1024;

    public function show(Request $request, ToolOffer $offer): Response
    {
        $person = $this->sessionPerson($request, $offer);

        if ($person === null) {
            $html = OfferToolPage::login([
                'url' => route('offer-tool.client.login', $offer, false),
                // Alleen de projectnaam, zodat de klant ziet dat hij goed zit. Verder
                // staat er niets van de offerte in deze pagina.
                'project' => $offer->project(),
                'email' => config('renovion.offer_tool.email'),
                'csrf' => csrf_token(),
            ]);
        } else {
            $html = OfferToolPage::tool($offer->number, [
                'mode' => 'klant',
                'model' => $offer->dataForClient(),
                'rev' => $offer->rev,
                'at' => $offer->saved_at?->toIso8601String(),
                'person' => [
                    'id' => (string) $person->id,
                    'naam' => (string) ($person->naam ?? ''),
                    'rol' => (string) ($person->rol ?? 'klant'),
                ],
                'signUrl' => route('offer-tool.client.sign', $offer, false),
                'csrf' => csrf_token(),
            ]);
        }

        return $this->private(response($html)->header('Content-Type', 'text/html; charset=utf-8'));
    }

    public function login(Request $request, ToolOffer $offer): JsonResponse
    {
        $max = (int) config('renovion.offer_tool.max_attempts');
        $decay = (int) config('renovion.offer_tool.decay_seconds');
        // Per adres én per offerte: een code van zes cijfers is anders te raden,
        // ook door iemand die steeds van adres wisselt.
        $keys = ['offer-tool-ip:'.sha1((string) $request->ip()), 'offer-tool-offer:'.$offer->getKey()];

        foreach ($keys as $key) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                return $this->private(response()->json(['error' => 'te_veel'], 429));
            }
        }

        $model = $offer->data();
        $person = OfferToolAccess::find($model, $this->text($request, 'tel'), $this->text($request, 'code'));

        if ($person === null) {
            foreach ($keys as $key) {
                RateLimiter::hit($key, $decay);
            }

            $this->log($offer, 'toegang_geweigerd', $request, ['reden' => 'onbekend']);

            return $this->private(response()->json(['error' => 'onbekend'], 401));
        }

        $state = OfferToolAccess::state($model, $person);

        if ($state !== OfferToolAccess::OPEN) {
            $this->log($offer, 'toegang_geweigerd', $request, ['reden' => $state, 'persoon' => $person->naam ?? null]);

            return $this->private(response()->json(array_filter([
                'error' => $state,
                'until' => $state === OfferToolAccess::EXPIRED ? ($person->until ?? null) : null,
                'from' => $state === OfferToolAccess::NOT_YET ? ($person->from ?? null) : null,
            ]), 403));
        }

        // Nieuw sessienummer na het inloggen, en de hash erbij: een vernieuwde
        // code maakt deze sessie dan vanzelf ongeldig.
        $request->session()->regenerate();
        $request->session()->put($this->sessionKey($offer), [
            'pid' => (string) $person->id,
            'h' => (string) $person->codeHash,
        ]);

        $this->log($offer, 'toegang', $request, ['persoon' => $person->naam ?? null, 'rol' => $person->rol ?? null]);

        return $this->private(response()->json(['redirect' => route('offer-tool.client', $offer, false)]));
    }

    /**
     * Het akkoord van de opdrachtgever, met handtekening. De server zet de
     * offerte op getekend en meldt het in het teamkanaal.
     */
    public function sign(Request $request, ToolOffer $offer): JsonResponse
    {
        $person = $this->sessionPerson($request, $offer);

        if ($person === null) {
            return $this->private(response()->json(['error' => 'geen_sessie'], 401));
        }

        // Een architect of meekijker kijkt mee; tekenen doet de opdrachtgever.
        if (($person->rol ?? 'klant') !== 'klant') {
            return $this->private(response()->json(['error' => 'geen_opdrachtgever'], 403));
        }

        $validated = $request->validate([
            'naam' => ['required', 'string', 'max:200'],
            'functie' => ['nullable', 'string', 'max:200'],
            'plaats' => ['nullable', 'string', 'max:200'],
            'sig' => ['required', 'string', 'max:'.self::MAX_SIGNATURE_BYTES],
            'nr' => ['nullable', 'string', 'max:60'],
            'versie' => ['nullable', 'integer'],
        ]);

        if (! $this->isPng($validated['sig'])) {
            return $this->private(response()->json(['error' => 'handtekening'], 422));
        }

        $today = now(OfferToolAccess::TIMEZONE)->toDateString();

        $result = DB::transaction(function () use ($offer, $person, $validated, $request, $today) {
            $current = ToolOffer::whereKey($offer->getKey())->lockForUpdate()->firstOrFail();
            $model = $current->data();
            $quote = $model?->offerte ?? null;

            // Tekenen kan alleen op de offerte die nu verzonden is, in de versie
            // die de klant voor zich had.
            if (! is_object($quote) || ($quote->status ?? null) !== 'verzonden') {
                return ($quote->status ?? null) === 'getekend' ? 'al_getekend' : 'niet_tekenbaar';
            }

            if ((string) ($validated['nr'] ?? '') !== (string) ($quote->nr ?? '')
                || (int) ($validated['versie'] ?? 0) !== (int) ($quote->versie ?? 0)) {
                return 'andere_versie';
            }

            $signature = $current->signatures()->create([
                'person_id' => (string) $person->id,
                'person_name' => $person->naam ?? null,
                'name' => $validated['naam'],
                'function' => $validated['functie'] ?? null,
                'place' => $validated['plaats'] ?? null,
                'signed_on' => $today,
                'offer_number' => $quote->nr ?? null,
                'version' => (int) ($quote->versie ?? 1),
                'signature' => $validated['sig'],
                'ip' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            ]);

            $quote->status = 'getekend';
            $quote->getekend = $today;
            $quote->getekendDoor = $validated['naam'];

            $current->putData($model);
            $current->rev++;
            $current->saved_at = now();
            $current->save();

            AuditLog::record($current, 'getekend', ['status' => 'verzonden'], [
                'status' => 'getekend',
                'naam' => $validated['naam'],
                'persoon' => $person->naam ?? null,
                'versie' => $signature->version,
                'ip' => $request->ip(),
            ], ActionSource::Website);

            return $signature;
        });

        if (is_string($result)) {
            return $this->private(response()->json(['error' => $result], 409));
        }

        // Het team laten weten, op de plek waar het de dag begint.
        ChatChannel::firstOrCreate(['type' => 'team', 'name' => 'Algemeen'])->messages()->create([
            'user_id' => null,
            'body' => sprintf(
                'Offerte %s versie %d is getekend door %s.',
                $result->offer_number ?: $offer->number,
                $result->version,
                $result->name,
            ),
            'nova' => ['executed' => true, 'url' => route('offer-tool.index')],
        ]);

        return $this->private(response()->json(['ok' => true]));
    }

    /**
     * Een bijlage. Voor het team dat de offertetool mag gebruiken, en voor een
     * klant met een geldige code op déze offerte.
     */
    public function file(Request $request, ToolOffer $offer, string $file): StreamedResponse
    {
        $team = $request->user() !== null && Gate::forUser($request->user())->allows('module', 'offertetool');

        abort_unless($team || $this->sessionPerson($request, $offer) !== null, 403);

        $path = 'offer-tool/'.$offer->getKey().'/'.$file;

        abort_unless(Storage::exists($path), 404);

        // Altijd als download en nooit als webpagina: wat iemand uploadt mag in
        // de browser niet als onze eigen pagina kunnen draaien. De tool zet er
        // zelf het juiste bestandstype weer op.
        return Storage::download($path, 'bijlage', [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    /**
     * De ingelogde persoon, als zijn toegang op dit moment nog geldig is.
     */
    private function sessionPerson(Request $request, ToolOffer $offer): ?object
    {
        $session = $request->session()->get($this->sessionKey($offer));

        if (! is_array($session)) {
            return null;
        }

        $model = $offer->data();
        $person = OfferToolAccess::person($model, $session['pid'] ?? null, $session['h'] ?? null);

        if ($person === null || OfferToolAccess::state($model, $person) !== OfferToolAccess::OPEN) {
            $request->session()->forget($this->sessionKey($offer));

            return null;
        }

        return $person;
    }

    /** Een veld als tekst, ook als iemand er iets anders in stopt. */
    private function text(Request $request, string $key): string
    {
        $value = $request->input($key);

        return is_scalar($value) ? (string) $value : '';
    }

    private function sessionKey(ToolOffer $offer): string
    {
        return 'offer_tool.'.$offer->getKey();
    }

    private function isPng(string $dataUrl): bool
    {
        $prefix = 'data:image/png;base64,';

        if (! str_starts_with($dataUrl, $prefix)) {
            return false;
        }

        $bytes = base64_decode(substr($dataUrl, strlen($prefix)), true);

        return $bytes !== false && str_starts_with($bytes, "\x89PNG\r\n\x1a\n");
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function log(ToolOffer $offer, string $action, Request $request, array $details): void
    {
        AuditLog::record($offer, $action, [], ['ip' => $request->ip(), ...array_filter($details)], ActionSource::Website);
    }

    /** Klantpagina's worden nergens bewaard en niet geïndexeerd. */
    private function private(Response|JsonResponse $response): Response|JsonResponse
    {
        return $response
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
