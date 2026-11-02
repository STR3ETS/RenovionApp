<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Een hoofdstuk in de begroting, met het nummer uit de standaard
 * (21 binnenwanden, 42 stucwerk) en de paragrafen eronder.
 */
#[Fillable(['budget_id', 'code', 'name', 'position'])]
class BudgetChapter extends Model
{
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function paragraphs(): HasMany
    {
        return $this->hasMany(BudgetParagraph::class)->orderBy('position')->orderBy('id');
    }

    public function lines(): HasManyThrough
    {
        return $this->hasManyThrough(BudgetLine::class, BudgetParagraph::class);
    }

    /** De som van de regels in dit hoofdstuk, tegen het tarief van de begroting. */
    public function total(?float $rate = null): float
    {
        $rate ??= (float) $this->budget->hourly_rate;

        return round($this->paragraphs->sum(fn (BudgetParagraph $paragraph) => $paragraph->total($rate)), 2);
    }
}
