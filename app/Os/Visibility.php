<?php

namespace App\Os;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Wat een gebruiker van de staat te zien krijgt en mag wijzigen.
 *
 * Interne rollen zien alles; het menu per rol regelt de app zelf (PERM). Een
 * klant of onderaannemer krijgt alleen wat bij zijn eigen project of eigen
 * dossier hoort, en mag alleen schrijven waar het portaal dat nodig heeft.
 * Dat wordt hier op de server afgedwongen, niet in de browser.
 */
final class Visibility
{
    /** @var array<int, array<string, mixed>> per gebruiker: wat hij mag zien */
    private array $scopes = [];

    public function read(User $user, string $path, string $collection, ?string $value): ?string
    {
        if (! $user->isExternal() || $value === null) {
            return $value;
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
            return true;
        }

        $scope = $this->scope($user);

        return match ($user->role) {
            UserRole::Klant => app(Portal\ClientScope::class)->write($scope, $path, $old, $new),
            UserRole::Onderaannemer => app(Portal\PartnerScope::class)->write($scope, $path, $old, $new),
            default => false,
        };
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
