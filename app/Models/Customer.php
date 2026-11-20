<?php

namespace App\Models;

use App\Enums\ActionSource;
use App\Enums\CustomerType;
use App\Enums\TimelineEventType;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

#[Fillable([
    'name', 'type', 'contact_name', 'email', 'phone', 'address', 'postal_code', 'city',
    'coc_number', 'vat_number', 'payment_days', 'reminders', 'relation', 'notes',
])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CustomerType::class,
            'payment_days' => 'integer',
            'reminders' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Elke klant krijgt een klantnummer. Dat nummer staat op de persoonlijke
        // portaallink en op de factuur.
        static::created(function (Customer $customer) {
            if ($customer->number === null) {
                $customer->forceFill(['number' => sprintf('K-%04d', $customer->id)])->saveQuietly();
            }
        });
    }

    public function frameworkAgreements(): HasMany
    {
        return $this->hasMany(FrameworkAgreement::class)->latest('starts_on');
    }

    /** De raamovereenkomst die nu loopt, als die er is. */
    public function activeFramework(): ?FrameworkAgreement
    {
        return $this->frameworkAgreements->first(fn (FrameworkAgreement $agreement) => $agreement->isRunning());
    }

    public function isBusiness(): bool
    {
        return $this->type === CustomerType::Zakelijk;
    }

    /**
     * De persoonlijke link waarmee een zakelijke klant zonder inloggen een
     * opdracht inschiet. Wordt pas gemaakt als iemand hem nodig heeft.
     */
    public function ensurePortalToken(): string
    {
        if ($this->portal_token === null) {
            $this->forceFill(['portal_token' => Str::random(48)])->save();
        }

        return $this->portal_token;
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * Het portaal-account van deze klant (briefing §10).
     */
    public function portalUser(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function timelineEvents(): HasMany
    {
        return $this->hasMany(TimelineEvent::class)->latest('happened_at');
    }

    /**
     * Voeg een gebeurtenis toe aan de klanttimeline (het centrale dossier).
     */
    public function recordEvent(
        TimelineEventType $type,
        string $title,
        ?string $body = null,
        ?Model $subject = null,
        ActionSource $source = ActionSource::Handmatig,
        ?array $meta = null,
    ): TimelineEvent {
        return $this->timelineEvents()->create([
            'user_id' => Auth::id(),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'type' => $type,
            'source' => $source,
            'title' => $title,
            'body' => $body,
            'happened_at' => now(),
            'meta' => $meta,
        ]);
    }
}
