<?php

namespace App\Models;

use App\Enums\AutomationMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'mode'])]
class AutomationSetting extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mode' => AutomationMode::class,
        ];
    }

    public static function modeFor(string $key, AutomationMode $default): AutomationMode
    {
        return self::query()->where('key', $key)->first()?->mode ?? $default;
    }
}
