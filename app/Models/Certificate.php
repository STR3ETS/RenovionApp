<?php

namespace App\Models;

use App\Support\CertificateCatalogue;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een certificaat van een eigen medewerker of van een onderaannemer:
 * VCA, BHV, NEN 3140 en zo verder. De geldigheidsdatum bepaalt of iemand
 * de bouwplaats op mag.
 */
#[Fillable(['user_id', 'subcontractor_id', 'type', 'number', 'issued_on', 'valid_until', 'path'])]
class Certificate extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['issued_on' => 'date', 'valid_until' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subcontractor(): BelongsTo
    {
        return $this->belongsTo(Subcontractor::class);
    }

    public function state(): string
    {
        return CertificateCatalogue::state($this->valid_until);
    }

    /**
     * @return array{short: string, years: int, about: string, colour: string}
     */
    public function meta(): array
    {
        return CertificateCatalogue::meta($this->type);
    }

    public function isValid(): bool
    {
        return $this->state() !== 'verlopen';
    }
}
