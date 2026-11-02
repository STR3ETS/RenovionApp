<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een vraag die de klant via de klantlink stelt. Dat is een sterk
 * signaal: wie vragen stelt, overweegt serieus.
 */
#[Fillable(['quote_id', 'body', 'asked_by', 'answered_at', 'answer', 'answered_by'])]
class QuoteQuestion extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['answered_at' => 'datetime'];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function answerer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }

    public function isOpen(): bool
    {
        return $this->answered_at === null;
    }
}
