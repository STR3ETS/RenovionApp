<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Afwezigheid: vakantie, ziekte of verlof. De planning rekent deze dagen
 * niet mee als beschikbare capaciteit.
 */
#[Fillable(['user_id', 'type', 'from', 'until', 'note'])]
class Absence extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['from' => 'date', 'until' => 'date'];
    }

    /** @var array<string, string> */
    public const TYPES = [
        'vakantie' => 'Vakantie',
        'ziekte' => 'Ziek',
        'verlof' => 'Verlof',
        'opleiding' => 'Opleiding',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /** Overlapt deze afwezigheid met de gegeven periode? */
    public function overlaps(\DateTimeInterface $from, \DateTimeInterface $until): bool
    {
        return $this->from->lessThanOrEqualTo($until) && $this->until->greaterThanOrEqualTo($from);
    }

    /** Het aantal werkdagen dat hierdoor wegvalt binnen een periode. */
    public function workdaysIn(Carbon $from, Carbon $until): int
    {
        if (! $this->overlaps($from, $until)) {
            return 0;
        }

        $start = $this->from->greaterThan($from) ? $this->from : $from;
        $end = $this->until->lessThan($until) ? $this->until : $until;

        return (int) $start->diffInWeekdays($end) + 1;
    }
}
