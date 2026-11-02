<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een optie of post meerwerk. Opties staan buiten de aanneemsom tot de klant
 * akkoord geeft; vanaf dat moment tellen ze als meerwerk mee, met dezelfde
 * staart als het reguliere werk.
 */
#[Fillable([
    'budget_id', 'description', 'note', 'quantity', 'unit', 'price',
    'status', 'decided_at', 'position',
])]
class BudgetOption extends Model
{
    public const STATUS_OPEN = 'optie';

    public const STATUS_APPROVED = 'akkoord';

    public const STATUS_DECLINED = 'afgewezen';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'price' => 'decimal:2',
            'decided_at' => 'datetime',
        ];
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'Akkoord',
            self::STATUS_DECLINED => 'Afgewezen',
            default => 'Open optie',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'bg-green-500/15 text-green-300',
            self::STATUS_DECLINED => 'bg-white/10 text-slate-400',
            default => 'bg-calc-500/15 text-calc-300',
        };
    }

    /** Een optie zonder prijs is nog niet uitgezocht en mag niet naar de klant. */
    public function needsPricing(): bool
    {
        return (float) $this->price === 0.0 && $this->status !== self::STATUS_DECLINED;
    }
}
