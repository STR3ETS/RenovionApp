<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\QueryException;

#[Fillable(['automation', 'subject_type', 'subject_id'])]
class AutomationRun extends Model
{
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Claim een eenmalige run voor dit onderwerp. Retourneert false als de
     * automation al eerder op dit onderwerp is uitgevoerd (idempotentie).
     */
    public static function claim(string $automation, Model $subject): bool
    {
        try {
            self::create([
                'automation' => $automation,
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
            ]);
        } catch (QueryException) {
            return false;
        }

        return true;
    }
}
