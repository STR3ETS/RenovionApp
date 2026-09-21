<?php

namespace App\Models;

use App\Enums\QuoteStatus;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'number', 'customer_id', 'lead_id', 'project_id', 'status',
    'subtotal', 'vat_amount', 'total', 'sent_at', 'viewed_at',
    'viewed_count', 'accepted_at', 'rejected_at', 'valid_until', 'notes',
])]
class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'subtotal' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'sent_at' => 'datetime',
            'viewed_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'valid_until' => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(QuoteLine::class)->orderBy('position');
    }

    #[Scope]
    protected function open(Builder $query): Builder
    {
        return $query->whereNotIn('status', [QuoteStatus::Akkoord, QuoteStatus::Afgewezen]);
    }

    /**
     * Herbereken subtotaal, BTW en totaal op basis van de offerteregels.
     */
    public function recalculateTotals(): void
    {
        $this->loadMissing('lines');

        $subtotal = $this->lines->sum(fn (QuoteLine $line): float => (float) $line->total);
        $vat = $this->lines->sum(
            fn (QuoteLine $line): float => (float) $line->total * ((float) $line->vat_rate / 100)
        );

        $this->forceFill([
            'subtotal' => round($subtotal, 2),
            'vat_amount' => round($vat, 2),
            'total' => round($subtotal + $vat, 2),
        ])->save();
    }

    /**
     * Aantal dagen dat de offerte openstaat sinds verzending.
     */
    public function daysOpen(): ?int
    {
        if ($this->sent_at === null || ! $this->status->isOpen()) {
            return null;
        }

        return (int) $this->sent_at->diffInDays(now());
    }

    public static function nextNumber(): string
    {
        $year = now()->year;
        $prefix = "OFF-{$year}-";

        $last = self::where('number', 'like', "{$prefix}%")
            ->orderByDesc('number')
            ->value('number');

        $sequence = $last === null ? 1 : ((int) str_replace($prefix, '', $last)) + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
