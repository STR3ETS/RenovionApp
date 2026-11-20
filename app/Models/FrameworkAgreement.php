<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Een raamovereenkomst met een zakelijke opdrachtgever: een looptijd, vaste
 * afspraken en vaste verkoopprijzen per artikel. Staat de preset aan, dan
 * begint een nieuwe begroting voor deze klant met die prijzen.
 */
#[Fillable([
    'customer_id', 'number', 'starts_on', 'ends_on', 'preset_active',
    'overhead_pct', 'profit_pct', 'payment_days', 'notes',
])]
class FrameworkAgreement extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'preset_active' => 'boolean',
            'overhead_pct' => 'decimal:2',
            'profit_pct' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(FrameworkPrice::class)->orderBy('position')->orderBy('id');
    }

    public function terms(): HasMany
    {
        return $this->hasMany(FrameworkTerm::class)->orderBy('position')->orderBy('id');
    }

    public static function nextNumber(): string
    {
        $year = now()->year;
        $last = self::where('number', 'like', "RO-{$year}-%")->orderByDesc('number')->value('number');
        $sequence = $last === null ? 0 : (int) str($last)->afterLast('-')->value();

        return sprintf('RO-%d-%03d', $year, $sequence + 1);
    }

    /** Loopt de overeenkomst op dit moment? */
    public function isRunning(): bool
    {
        return $this->starts_on->lessThanOrEqualTo(today())
            && ($this->ends_on === null || $this->ends_on->greaterThanOrEqualTo(today()));
    }

    public function daysLeft(): ?int
    {
        return $this->ends_on === null ? null : (int) today()->diffInDays($this->ends_on, false);
    }
}
