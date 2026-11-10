<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Een paragraaf binnen een hoofdstuk: de kop waaronder een reeks regels
 * staat, bijvoorbeeld "Voorzetwanden begane grond".
 */
#[Fillable(['budget_chapter_id', 'code', 'name', 'position'])]
class BudgetParagraph extends Model
{
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(BudgetChapter::class, 'budget_chapter_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BudgetLine::class)->orderBy('position')->orderBy('id');
    }

    public function total(float $rate): float
    {
        return round($this->lines
            ->filter(fn (BudgetLine $line) => $line->countsTowardsTotal())
            ->sum(fn (BudgetLine $line) => $line->costs($rate)['totaal']), 2);
    }
}
