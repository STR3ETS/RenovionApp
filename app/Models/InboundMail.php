<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een bericht in de aanvraagmail: een mail aan aanvraag@, of een opdracht die
 * een zakelijke klant via zijn persoonlijke link inschoot. Nova leest eruit
 * wat er gevraagd wordt; een mens koppelt het aan een klant en zet het uit.
 */
#[Fillable([
    'from_name', 'from_email', 'subject', 'body', 'status', 'source',
    'customer_id', 'lead_id', 'extracted', 'message_id', 'received_at', 'handled_by',
])]
class InboundMail extends Model
{
    public const STATUS_NEW = 'nieuw';

    public const STATUS_LINKED = 'gekoppeld';

    public const STATUS_DISPATCHED = 'uitgezet';

    /** @var array<string, string> */
    public const STATUSES = [
        self::STATUS_NEW => 'Nieuw',
        self::STATUS_LINKED => 'Gekoppeld',
        self::STATUS_DISPATCHED => 'Uitgezet',
    ];

    public const SOURCE_MAIL = 'mail';

    public const SOURCE_PORTAL = 'portaal';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['extracted' => 'array', 'received_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /** Wat nog op iemand wacht: nieuw of gekoppeld maar nog niet uitgezet. */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereIn('status', [self::STATUS_NEW, self::STATUS_LINKED]);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function badgeClasses(): string
    {
        return match ($this->status) {
            self::STATUS_LINKED => 'bg-lead-500/15 text-lead-300',
            self::STATUS_DISPATCHED => 'bg-green-500/15 text-green-300',
            default => 'bg-brand-500/15 text-brand-300',
        };
    }

    public function fromPortal(): bool
    {
        return $this->source === self::SOURCE_PORTAL;
    }

    /** Een veld dat Nova uit het bericht las, of leeg als dat er niet in stond. */
    public function read(string $key): ?string
    {
        $value = $this->extracted[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** Was de klant er al voordat dit bericht binnenkwam? Dan slaan we de aanvraagfase over. */
    public function isForExistingCustomer(): bool
    {
        return $this->customer_id !== null && ! ($this->extracted['nieuwe_klant'] ?? false);
    }
}
