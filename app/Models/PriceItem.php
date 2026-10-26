<?php

namespace App\Models;

use App\Enums\CalculationLineType;
use Database\Factories\PriceItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'source', 'edition', 'code', 'name', 'type', 'unit',
    'unit_price', 'surcharge_pct', 'index_factor', 'active', 'last_checked_at',
])]
class PriceItem extends Model
{
    /** @use HasFactory<PriceItemFactory> */
    use HasFactory;

    /**
     * Artikelnummer-prefix per type (prijslijst): ARB-001, MAT-014, enz.
     */
    public const CODE_PREFIXES = [
        'arbeid' => 'ARB',
        'materiaal' => 'MAT',
        'materieel' => 'MTR',
        'onderaannemer' => 'OND',
        'stelpost' => 'STP',
    ];

    protected static function booted(): void
    {
        static::creating(function (PriceItem $item) {
            if (blank($item->code)) {
                $item->code = self::nextCode($item->type?->value ?? 'arbeid');
            }
        });
    }

    /**
     * Volgend vrij artikelnummer voor een type.
     */
    public static function nextCode(string $type): string
    {
        $prefix = self::CODE_PREFIXES[$type] ?? 'ART';

        $highest = self::where('code', 'like', $prefix.'-%')
            ->get(['code'])
            ->map(fn (PriceItem $item) => (int) str($item->code)->after('-')->value())
            ->max() ?? 0;

        return sprintf('%s-%03d', $prefix, $highest + 1);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CalculationLineType::class,
            'unit_price' => 'decimal:2',
            'surcharge_pct' => 'decimal:2',
            'index_factor' => 'decimal:3',
            'active' => 'boolean',
            'last_checked_at' => 'date',
        ];
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * Kale prijs × regio-/indexfactor (briefing §5).
     */
    public function effectivePrice(): float
    {
        return round((float) $this->unit_price * (float) $this->index_factor, 2);
    }
}
