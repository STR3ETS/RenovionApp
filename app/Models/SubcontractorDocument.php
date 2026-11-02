<?php

namespace App\Models;

use App\Support\CertificateCatalogue;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een dossierstuk van een onderaannemer: een document met een
 * geldigheidsdatum, of een overeenkomst met een ondertekening.
 */
#[Fillable(['subcontractor_id', 'type', 'path', 'valid_until', 'signed_at', 'signed_by'])]
class SubcontractorDocument extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['valid_until' => 'date', 'signed_at' => 'datetime'];
    }

    public function subcontractor(): BelongsTo
    {
        return $this->belongsTo(Subcontractor::class);
    }

    public function state(): string
    {
        return CertificateCatalogue::state($this->valid_until);
    }

    public function isAgreement(): bool
    {
        return array_key_exists($this->type, CertificateCatalogue::PARTNER_AGREEMENTS);
    }
}
