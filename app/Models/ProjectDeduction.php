<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Minderwerk: werk dat vervalt en van de aanneemsom af gaat. Pas na
 * akkoord van de klant telt het mee in de eindafrekening.
 */
#[Fillable(['project_id', 'description', 'amount', 'status', 'decided_at'])]
class ProjectDeduction extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'decided_at' => 'datetime'];
    }

    public const STATUS_PROPOSED = 'voorstel';

    public const STATUS_AGREED = 'akkoord';

    public const STATUS_DECLINED = 'afgewezen';

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function isAgreed(): bool
    {
        return $this->status === self::STATUS_AGREED;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_AGREED => 'Akkoord',
            self::STATUS_DECLINED => 'Afgewezen',
            default => 'Voorstel',
        };
    }
}
