<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een inkoopfactuur van een leverancier of onderaannemer. Na goedkeuring telt
 * het bedrag mee als werkelijke kosten op het project, in de kostensoort waar
 * het hoort: materiaal, materieel of onderaanneming.
 */
#[Fillable([
    'number', 'supplier', 'project_id', 'purchase_order_id', 'category',
    'date', 'due_date', 'amount', 'vat_pct', 'status',
    'approved_at', 'approved_by', 'paid_at', 'notes',
])]
class PurchaseInvoice extends Model
{
    public const STATUS_NEW = 'nieuw';

    public const STATUS_APPROVED = 'goedgekeurd';

    public const STATUS_PAID = 'betaald';

    public const STATUS_DISPUTED = 'afgewezen';

    /** @var array<string, string> */
    public const STATUSES = [
        self::STATUS_NEW => 'Nieuw',
        self::STATUS_APPROVED => 'Goedgekeurd',
        self::STATUS_PAID => 'Betaald',
        self::STATUS_DISPUTED => 'Afgewezen',
    ];

    /** @var array<string, string> */
    public const CATEGORIES = [
        'materiaal' => 'Materiaal',
        'materieel' => 'Materieel en verhuur',
        'onderaanneming' => 'Onderaanneming',
        'overig' => 'Overig',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'vat_pct' => 'decimal:2',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Goedgekeurd en betaald tellen mee als kosten. */
    #[Scope]
    protected function booked(Builder $query): void
    {
        $query->whereIn('status', [self::STATUS_APPROVED, self::STATUS_PAID]);
    }

    #[Scope]
    protected function unpaid(Builder $query): void
    {
        $query->whereIn('status', [self::STATUS_NEW, self::STATUS_APPROVED]);
    }

    public function isBooked(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_PAID], true);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function badgeClasses(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'bg-lead-500/15 text-lead-300',
            self::STATUS_PAID => 'bg-green-500/15 text-green-300',
            self::STATUS_DISPUTED => 'bg-red-500/15 text-red-300',
            default => 'bg-amber-500/15 text-amber-300',
        };
    }

    public function total(): float
    {
        return round((float) $this->amount * (1 + (float) $this->vat_pct / 100), 2);
    }

    public function isOverdue(): bool
    {
        return $this->status !== self::STATUS_PAID
            && $this->status !== self::STATUS_DISPUTED
            && $this->due_date->isPast();
    }
}
