<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een akkoord van de klant op een offerte uit de offertetool, met handtekening.
 */
#[Fillable([
    'tool_offer_id', 'person_id', 'person_name', 'name', 'function', 'place',
    'signed_on', 'offer_number', 'version', 'signature', 'ip', 'user_agent',
])]
class ToolOfferSignature extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'signed_on' => 'date',
            'version' => 'integer',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(ToolOffer::class, 'tool_offer_id');
    }
}
