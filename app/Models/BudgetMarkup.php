<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een staartpost: algemene kosten, winst en risico, of verzekering. Ze
 * rekenen cumulatief door, elk over het bedrag inclusief de vorige.
 */
#[Fillable(['budget_id', 'name', 'percentage', 'position'])]
class BudgetMarkup extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['percentage' => 'decimal:3'];
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }
}
