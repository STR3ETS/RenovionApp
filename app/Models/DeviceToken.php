<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Firebase-token van een telefoon (iOS/Android-app) waarop de gebruiker is ingelogd.
 * Eén token hoort bij één gebruiker: wie op dezelfde telefoon inlogt, neemt het over.
 */
#[Fillable(['user_id', 'token', 'platform'])]
class DeviceToken extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
