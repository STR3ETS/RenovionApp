<?php

namespace App\Models;

use App\Support\CertificateCatalogue;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Een onderaannemer. Voordat iemand de bouwplaats op mag, moet het dossier rond
 * zijn: uittreksel, legitimatie, VCA, verzekering, G-rekening en de vier
 * overeenkomsten. Zolang daar iets aan mankeert, nodigen we hem niet uit.
 */
#[Fillable([
    'name', 'contact_name', 'email', 'phone', 'city', 'coc_number', 'vat_number',
    'trades', 'hourly_rate', 'region', 'rating', 'assignments', 'blocked',
    'blocked_reason', 'id_checked', 'user_id', 'notes',
])]
class Subcontractor extends Model
{
    public const STATUS_APPROVED = 'goedgekeurd';

    public const STATUS_INCOMPLETE = 'dossier_incompleet';

    public const STATUS_EXPIRED = 'document_verlopen';

    public const STATUS_BLOCKED = 'geblokkeerd';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trades' => 'array',
            'hourly_rate' => 'decimal:2',
            'blocked' => 'boolean',
            'id_checked' => 'boolean',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SubcontractorDocument::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bids(): HasMany
    {
        return $this->hasMany(RfqBid::class);
    }

    public function awardedRfqs(): HasMany
    {
        return $this->hasMany(Rfq::class, 'awarded_to');
    }

    /** Partijen die we mogen uitnodigen voor werk. */
    #[Scope]
    protected function available(Builder $query): void
    {
        $query->where('blocked', false);
    }

    /**
     * Het dossier langs de eisen. Elk punt is in orde, verlopen of ontbreekt.
     *
     * @return array{items: list<array{key: string, label: string, state: string, until: ?Carbon}>, open: list<array<string, mixed>>, complete: bool}
     */
    public function dossier(): array
    {
        $docs = $this->documents->keyBy('type');
        $items = [];

        $items[] = [
            'key' => 'ID',
            'label' => 'Legitimatie gecontroleerd',
            'state' => $this->id_checked ? 'ok' : 'ontbreekt',
            'until' => null,
        ];

        foreach (CertificateCatalogue::PARTNER_DOCUMENTS as $type => $label) {
            $doc = $docs->get($type);

            $items[] = [
                'key' => $type,
                'label' => $label,
                'state' => CertificateCatalogue::state($doc?->valid_until, $doc !== null),
                'until' => $doc?->valid_until,
            ];
        }

        foreach (CertificateCatalogue::PARTNER_AGREEMENTS as $type => $label) {
            $doc = $docs->get($type);

            $items[] = [
                'key' => $type,
                'label' => $label,
                'state' => $doc?->signed_at !== null ? 'ok' : 'ontbreekt',
                'until' => null,
            ];
        }

        $open = array_values(array_filter($items, fn (array $item) => $item['state'] !== 'ok'));

        return ['items' => $items, 'open' => $open, 'complete' => $open === []];
    }

    /**
     * De keuringsstatus. Geblokkeerd weegt het zwaarst, dan een verlopen stuk,
     * dan een ontbrekend stuk.
     */
    public function status(): string
    {
        if ($this->blocked) {
            return self::STATUS_BLOCKED;
        }

        $dossier = $this->dossier();

        if (collect($dossier['items'])->contains('state', 'verlopen')) {
            return self::STATUS_EXPIRED;
        }

        return $dossier['complete'] ? self::STATUS_APPROVED : self::STATUS_INCOMPLETE;
    }

    public function statusLabel(): string
    {
        return match ($this->status()) {
            self::STATUS_BLOCKED => 'Geblokkeerd',
            self::STATUS_EXPIRED => 'Document verlopen',
            self::STATUS_INCOMPLETE => 'Dossier incompleet',
            default => 'Goedgekeurd',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this->status()) {
            self::STATUS_BLOCKED, self::STATUS_EXPIRED => 'bg-red-500/15 text-red-300',
            self::STATUS_INCOMPLETE => 'bg-amber-500/15 text-amber-300',
            default => 'bg-green-500/15 text-green-300',
        };
    }

    /** Alleen een goedgekeurde, niet geblokkeerde partij mag werk aangeboden krijgen. */
    public function mayBeInvited(): bool
    {
        return $this->status() === self::STATUS_APPROVED;
    }
}
