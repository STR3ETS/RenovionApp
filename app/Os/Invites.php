<?php

namespace App\Os;

use App\Enums\UserRole;
use App\Mail\AccountInvitation;
use App\Models\OsRow;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Uitnodigingen voor een nieuw account (of een nieuw tijdelijk wachtwoord):
 * een mail met een persoonlijke link waarmee iemand zonder wachtwoord
 * binnenkomt en meteen een eigen wachtwoord kiest. Dezelfde link komt terug
 * naar het scherm, zodat de directie hem ook zelf kan doorsturen.
 *
 * De link werkt zeven dagen en alleen zolang het tijdelijke wachtwoord nog
 * geldt: na het kiezen van een eigen wachtwoord, een nieuw tijdelijk
 * wachtwoord of het sluiten van het account is hij dood.
 */
final class Invites
{
    public const DAYS = 7;

    /** Is er een mailserver gekoppeld? Met de mailer "log" gaat er niets de deur uit. */
    public static function enabled(): bool
    {
        return ! in_array(config('mail.default'), [null, '', 'log'], true);
    }

    public static function link(User $account): string
    {
        // Alleen het pad is ondertekend: de link blijft kloppen achter een proxy of bij http/https-verschil.
        return url(URL::temporarySignedRoute(
            'invite',
            now()->addDays(self::DAYS),
            ['user' => $account->id, 'h' => self::stamp($account)],
            absolute: false,
        ));
    }

    public static function valid(Request $request, User $account): bool
    {
        return $request->hasValidRelativeSignature()
            && $account->active
            && $account->must_change_password
            && hash_equals(self::stamp($account), (string) $request->query('h'));
    }

    /**
     * Wat het scherm terugkrijgt na het aanmaken van een account of een nieuw
     * tijdelijk wachtwoord. De mail gaat hier meteen de deur uit.
     *
     * @return array{naam: string, mail: string, wachtwoord: string, link: string, gemaild: bool}
     */
    public static function describe(User $account, string $password, ?User $by = null, bool $again = false): array
    {
        return [
            'naam' => (string) $account->name,
            'mail' => (string) $account->email,
            'wachtwoord' => $password,
            'link' => self::link($account),
            'gemaild' => self::send($account, $by, $again),
        ];
    }

    /** Verstuur de uitnodiging. Lukt dat niet, dan blijft opslaan gewoon werken en meldt het scherm het. */
    public static function send(User $account, ?User $by = null, bool $again = false): bool
    {
        if (! self::enabled()) {
            return false;
        }

        try {
            Mail::to($account->email)->send(new AccountInvitation(
                account: $account,
                link: self::link($account),
                kind: self::kind($account),
                again: $again,
                project: self::projectName($account),
                by: $by && $by->id !== $account->id ? (string) $by->name : null,
            ));

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /** klant, partner of team: bepaalt de aanspreekvorm en wat de mail belooft. */
    public static function kind(User $account): string
    {
        return match ($account->role) {
            UserRole::Klant => 'klant',
            UserRole::Onderaannemer => 'partner',
            default => 'team',
        };
    }

    /** De naam van het project waar een klant toegang toe krijgt. */
    private static function projectName(User $account): ?string
    {
        if ($account->role !== UserRole::Klant || ! $account->person_id) {
            return null;
        }

        $person = json_decode((string) OsRow::query()->where('path', 'extraUsers/'.$account->person_id)->value('value'), true);
        $scope = is_array($person) ? (string) ($person['scope'] ?? '') : '';
        if ($scope === '' || $scope === 'alle') {
            return null;
        }

        $project = json_decode((string) OsRow::query()->where('path', 'projects/'.$scope)->value('value'), true);

        return is_array($project) && ! empty($project['naam']) ? (string) $project['naam'] : null;
    }

    /** Verandert zodra het wachtwoord verandert: een oude link werkt dan niet meer. */
    private static function stamp(User $account): string
    {
        return substr(hash_hmac('sha256', (string) $account->password, (string) config('app.key')), 0, 20);
    }
}
