<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een vaste afspraak uit een raamovereenkomst, in gewone taal.
 */
#[Fillable(['framework_agreement_id', 'body', 'position'])]
class FrameworkTerm extends Model
{
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(FrameworkAgreement::class, 'framework_agreement_id');
    }
}
