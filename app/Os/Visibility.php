<?php

namespace App\Os;

use App\Enums\UserRole;
use App\Models\OsRow;
use App\Models\User;

/**
 * Wat een gebruiker van de staat te zien krijgt en mag wijzigen.
 *
 * Interne rollen zien alles, behalve besloten gesprekken in de teamchat waar ze
 * niet in zitten; het menu per rol regelt de app zelf (PERM). Een
 * klant of onderaannemer krijgt alleen wat bij zijn eigen project of eigen
 * dossier hoort, en mag alleen schrijven waar het portaal dat nodig heeft.
 * Dat wordt hier op de server afgedwongen, niet in de browser.
 */
final class Visibility
{
    /** @var array<int, array<string, mixed>> per gebruiker: wat hij mag zien */
    private array $scopes = [];

    /** @var array<int, list<string>> per gebruiker: besloten chatkanalen waar hij niet in zit */
    private array $closed = [];

    public function read(User $user, string $path, string $collection, ?string $value): ?string
    {
        if ($value === null) {
            return $value;
        }
        if (! $user->isExternal()) {
            return $this->closedChat($user, $path, $collection, $value) ? null : $value;
        }

        $scope = $this->scope($user);

        return match ($user->role) {
            UserRole::Klant => app(Portal\ClientScope::class)->read($scope, $path, $collection, $value),
            UserRole::Onderaannemer => app(Portal\PartnerScope::class)->read($scope, $path, $collection, $value),
            default => null,
        };
    }

    /**
     * true = opslaan zoals aangeleverd, string = deze (bijgesneden) waarde
     * opslaan, false = weigeren.
     */
    public function acceptWrite(User $user, string $path, ?string $old, ?string $new): bool|string
    {
        if (! $user->isExternal()) {
            // Een besloten gesprek waar je niet in zit, kun je ook niet wijzigen en je kunt er niet in schrijven.
            return ! ($old !== null && $this->closedChat($user, $path, Layout::collectionOf($path), $old))
                && ! ($new !== null && str_starts_with($path, 'chat.msgs/') && $this->closedChat($user, $path, 'chat.msgs', $new));
        }

        $scope = $this->scope($user);

        return match ($user->role) {
            UserRole::Klant => app(Portal\ClientScope::class)->write($scope, $path, $old, $new),
            UserRole::Onderaannemer => app(Portal\PartnerScope::class)->write($scope, $path, $old, $new),
            default => false,
        };
    }

    /**
     * Besloten gesprekken (teamchat, kanaal met `leden`): opnieuw inlezen voor deze
     * gebruiker. StateStore roept dit aan voordat het regels uitlevert.
     */
    public function refresh(User $user): void
    {
        if ($user->isExternal()) {
            return;
        }
        $ids = [];
        foreach (OsRow::query()->where('collection', 'chat.channels')->where('value', 'like', '%"leden"%')->pluck('value') as $json) {
            $channel = json_decode((string) $json, true);
            if (is_array($channel) && ! empty($channel['leden']) && is_array($channel['leden']) && ! in_array($user->person_id, $channel['leden'], true)) {
                $ids[] = (string) ($channel['id'] ?? '');
            }
        }
        $this->closed[$user->id] = array_values(array_filter($ids));
    }

    /** Is dit een besloten kanaal, of een bericht daarin, waar deze interne gebruiker niet bij hoort? */
    private function closedChat(User $user, string $path, string $collection, string $value): bool
    {
        if (($collection !== 'chat.channels' && $collection !== 'chat.msgs') || str_ends_with($path, '/#order')) {
            return false;
        }
        if (! isset($this->closed[$user->id])) {
            $this->refresh($user);
        }
        if ($collection === 'chat.channels') {
            $channel = json_decode($value, true);

            return is_array($channel) && ! empty($channel['leden']) && is_array($channel['leden']) && ! in_array($user->person_id, $channel['leden'], true);
        }
        if (! $this->closed[$user->id]) {
            return false;
        }
        $message = json_decode($value, true);

        return is_array($message) && in_array((string) ($message['ch'] ?? ''), $this->closed[$user->id], true);
    }

    /** @return array<string, mixed> */
    public function scope(User $user): array
    {
        return $this->scopes[$user->id] ??= match ($user->role) {
            UserRole::Klant => Portal\ClientScope::for($user),
            UserRole::Onderaannemer => Portal\PartnerScope::for($user),
            default => [],
        };
    }
}
