<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Het logboek van een onderaannemer: gegund, niet gegund, overeenkomst
 * vastgelegd. Zo is later na te gaan waarom een partij werk kreeg of niet.
 */
#[Fillable(['subcontractor_id', 'project_id', 'body', 'user_id', 'logged_at'])]
class SubcontractorLog extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['logged_at' => 'datetime'];
    }

    public function subcontractor(): BelongsTo
    {
        return $this->belongsTo(Subcontractor::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function note(Subcontractor $subcontractor, string $body, ?int $projectId = null, ?User $user = null): self
    {
        return self::create([
            'subcontractor_id' => $subcontractor->id,
            'project_id' => $projectId,
            'body' => mb_substr($body, 0, 255),
            'user_id' => $user?->id,
            'logged_at' => now(),
        ]);
    }
}
