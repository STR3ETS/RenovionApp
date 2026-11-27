<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Een inlogaccount. Wie iemand is binnen Renovion Groep (naam, vak, rol, rechten)
 * staat in de staat van het systeem; person_id wijst naar die persoon
 * (een medewerker of een extra gebruiker zoals een klant).
 */
#[Fillable(['name', 'email', 'password', 'role', 'phone', 'active', 'person_id', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $attributes = [
        'role' => 'uitvoerder',
        'active' => true,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    public function isExternal(): bool
    {
        return $this->role->isExternal();
    }
}
