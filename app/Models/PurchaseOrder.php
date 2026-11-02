<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Een inkooporder: wat er bij welke leverancier besteld is, voor welk project en
 * tegen welke prijs. Een openstaande order is een verplichting die in de
 * nacalculatie meetelt, ook al is er nog geen factuur.
 */
#[Fillable([
    'number', 'project_id', 'supplier', 'reference', 'status', 'ordered_on',
    'expected_on', 'delivered_on', 'chapter_code', 'category', 'notes', 'created_by',
])]
class PurchaseOrder extends Model
{
    public const STATUS_DRAFT = 'concept';

    public const STATUS_SENT = 'verzonden';

    public const STATUS_CONFIRMED = 'bevestigd';

    public const STATUS_DELIVERED = 'geleverd';

    /** @var array<string, string> */
    public const STATUSES = [
        self::STATUS_DRAFT => 'Concept',
        self::STATUS_SENT => 'Verzonden',
        self::STATUS_CONFIRMED => 'Bevestigd',
        self::STATUS_DELIVERED => 'Geleverd',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ordered_on' => 'date',
            'expected_on' => 'date',
            'delivered_on' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class)->orderBy('position')->orderBy('id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Orders waarvoor nog geen factuur binnen is: dat is de openstaande verplichting. */
    #[Scope]
    protected function uninvoiced(Builder $query): void
    {
        $query->whereDoesntHave('invoices');
    }

    public static function nextNumber(): string
    {
        $year = now()->year;
        $last = self::where('number', 'like', "INK-{$year}-%")
            ->orderByDesc('number')
            ->value('number');

        $sequence = $last === null ? 0 : (int) str($last)->afterLast('-')->value();

        return sprintf('INK-%d-%04d', $year, $sequence + 1);
    }

    public function total(): float
    {
        return round($this->lines->sum(fn (PurchaseOrderLine $line) => $line->total()), 2);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function badgeClasses(): string
    {
        return match ($this->status) {
            self::STATUS_SENT => 'bg-lead-500/15 text-lead-300',
            self::STATUS_CONFIRMED => 'bg-calc-500/15 text-calc-300',
            self::STATUS_DELIVERED => 'bg-green-500/15 text-green-300',
            default => 'bg-white/10 text-slate-300',
        };
    }

    /**
     * Een bestelling die er volgens de verwachte leverdatum had moeten zijn,
     * houdt het werk op de bouwplaats op.
     */
    public function isLate(): bool
    {
        return $this->status !== self::STATUS_DELIVERED
            && $this->expected_on !== null
            && $this->expected_on->isPast();
    }
}
