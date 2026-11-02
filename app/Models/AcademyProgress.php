<?php

namespace App\Models;

use App\Support\Academy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hoe ver iemand is in een rondleiding. Niets wordt handmatig afgevinkt: het
 * volgt uit welke stap je als laatste opende.
 */
#[Fillable(['user_id', 'tour', 'step', 'started_at', 'completed_at'])]
class AcademyProgress extends Model
{
    protected $table = 'academy_progress';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }

    public function tourTitle(): string
    {
        return Academy::TOURS[$this->tour]['title'] ?? $this->tour;
    }

    /**
     * De voortgang van een gebruiker over alle rondleidingen, als tour => staat.
     *
     * @return array<string, array{started: bool, done: bool, step: int}>
     */
    public static function forUser(User $user): array
    {
        return self::where('user_id', $user->id)
            ->get()
            ->mapWithKeys(fn (self $row) => [$row->tour => [
                'started' => true,
                'done' => $row->isComplete(),
                'step' => $row->step,
            ]])
            ->all();
    }
}
