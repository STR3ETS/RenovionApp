<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een termijn uit het betaalschema: een percentage van de aanneemsom, gekoppeld
 * aan een mijlpaal ("fundering gereed"). Een vaste termijn rekent met een bedrag
 * in plaats van een percentage, bijvoorbeeld voor een aanbetaling.
 */
#[Fillable([
    'budget_id', 'description', 'percentage', 'fixed', 'amount',
    'status', 'due_date', 'position',
])]
class BudgetTerm extends Model
{
    public const STATUS_PLANNED = 'gepland';

    public const STATUS_DUE = 'vervallen';

    public const STATUS_INVOICED = 'gefactureerd';

    public const STATUS_PAID = 'betaald';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:3',
            'fixed' => 'boolean',
            'amount' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DUE => 'Vervallen',
            self::STATUS_INVOICED => 'Gefactureerd',
            self::STATUS_PAID => 'Betaald',
            default => 'Gepland',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this->status) {
            self::STATUS_PAID => 'bg-green-500/15 text-green-300',
            self::STATUS_INVOICED => 'bg-lead-500/15 text-lead-300',
            self::STATUS_DUE => 'bg-amber-500/15 text-amber-300',
            default => 'bg-white/10 text-slate-300',
        };
    }
}
