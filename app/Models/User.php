<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name', 'email', 'password', 'role', 'phone', 'customer_id', 'motion_enabled',
    'job_title', 'trades', 'hourly_rate', 'weekly_capacity', 'plannable', 'started_on', 'active',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Spiegelt de databasedefault zodat ongepersisteerde/verse modellen ook een rol hebben.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'uitvoerder',
        'motion_enabled' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'onboarding_dismissed_at' => 'datetime',
            'motion_enabled' => 'boolean',
            'trades' => 'array',
            'hourly_rate' => 'decimal:2',
            'plannable' => 'boolean',
            'started_on' => 'date',
            'active' => 'boolean',
        ];
    }

    /** Het tarief waarmee uren naar kosten gaan; zonder tarief rekenen we met 50 euro. */
    public function rate(): float
    {
        return (float) ($this->hourly_rate ?? 50);
    }

    /** Alleen wie planbaar is, verschijnt in de planning en de capaciteitsweergave. */
    public function isPlannable(): bool
    {
        return $this->plannable && $this->active && ! $this->role->isExternal();
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'owner_id');
    }

    public function scheduleEntries(): HasMany
    {
        return $this->hasMany(ScheduleEntry::class);
    }

    #[Scope]
    protected function uitvoerders(Builder $query): Builder
    {
        return $query->where('role', UserRole::Uitvoerder);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }
}
