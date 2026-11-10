<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een keer dat de klantlink van een offerte geopend is. Of en hoe vaak
 * iemand kijkt, zegt iets over de kans dat hij tekent.
 */
#[Fillable(['quote_id', 'viewed_at', 'ip', 'user_agent'])]
class QuoteView extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['viewed_at' => 'datetime'];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }
}
