<?php

namespace App\Models;

use App\Enums\ActionSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;

#[Fillable(['user_id', 'auditable_type', 'auditable_id', 'action', 'old_values', 'new_values', 'source'])]
class AuditLog extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'source' => ActionSource::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Leg een belangrijke actie vast: wie → wat → wanneer → oude/nieuwe waarde → bron.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public static function record(
        Model $auditable,
        string $action,
        array $old = [],
        array $new = [],
        ActionSource $source = ActionSource::Handmatig,
    ): self {
        return self::create([
            'user_id' => Auth::id(),
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'action' => $action,
            'old_values' => $old !== [] ? $old : null,
            'new_values' => $new !== [] ? $new : null,
            'source' => $source,
        ]);
    }
}
