<?php

namespace App\Models;

use App\Support\BudgetTotals;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Een begroting met hoofdstukken. Concept betekent dat eraan gewerkt wordt;
 * definitief zet de regels op slot, zodat de offerte en de nacalculatie naar
 * hetzelfde document verwijzen.
 */
#[Fillable([
    'number', 'project_id', 'lead_id', 'customer_id', 'name', 'status', 'version',
    'hourly_rate', 'vat_pct', 'floor_area', 'location', 'author', 'dated_on',
    'scope', 'notes', 'finalised_at', 'finalised_by', 'created_by',
])]
class Budget extends Model
{
    public const STATUS_DRAFT = 'concept';

    public const STATUS_FINAL = 'definitief';

    /** De staart waarmee een nieuwe begroting begint (briefing: AK 8 procent, W&R 7 procent). */
    public const DEFAULT_MARKUPS = [
        ['name' => 'Algemene kosten', 'percentage' => 8],
        ['name' => 'Winst en risico', 'percentage' => 7],
        ['name' => 'CAR-verzekering', 'percentage' => 0.35],
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hourly_rate' => 'decimal:2',
            'vat_pct' => 'decimal:2',
            'floor_area' => 'decimal:2',
            'dated_on' => 'date',
            'scope' => 'array',
            'finalised_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function chapters(): HasMany
    {
        return $this->hasMany(BudgetChapter::class)->orderBy('position')->orderBy('code');
    }

    public function markups(): HasMany
    {
        return $this->hasMany(BudgetMarkup::class)->orderBy('position')->orderBy('id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(BudgetOption::class)->orderBy('position')->orderBy('id');
    }

    public function terms(): HasMany
    {
        return $this->hasMany(BudgetTerm::class)->orderBy('position')->orderBy('id');
    }

    public function finaliser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalised_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    #[Scope]
    protected function draft(Builder $query): void
    {
        $query->where('status', self::STATUS_DRAFT);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /** De volledige doorrekening. Eén aanroep, alle getallen. */
    public function totals(): BudgetTotals
    {
        return BudgetTotals::for($this);
    }

    /**
     * Doorlopende nummering per jaar: BEG-2026-0001.
     */
    public static function nextNumber(): string
    {
        $year = now()->year;
        $last = self::where('number', 'like', "BEG-{$year}-%")
            ->orderByDesc('number')
            ->value('number');

        $sequence = $last === null ? 0 : (int) str($last)->afterLast('-')->value();

        return sprintf('BEG-%d-%04d', $year, $sequence + 1);
    }

    /**
     * Wat er wel in zit, wat niet, en op welke stukken de begroting rust. Dit
     * gaat ongewijzigd mee naar de offerte, zodat de klant precies dezelfde
     * uitsluitingen leest als de calculator bedoelde.
     *
     * @return array{docs: list<string>, incl: list<string>, excl: list<string>}
     */
    public function scopeLists(): array
    {
        return [
            'docs' => $this->scope['docs'] ?? [],
            'incl' => $this->scope['incl'] ?? [],
            'excl' => $this->scope['excl'] ?? [],
        ];
    }

    /** Het bedrag van een termijn: vast bedrag, of een percentage van de aanneemsom. */
    public function termAmount(BudgetTerm $term, ?BudgetTotals $totals = null): float
    {
        if ($term->fixed) {
            return (float) $term->amount;
        }

        $totals ??= $this->totals();

        return round($totals->contractSum() * (float) $term->percentage / 100, 2);
    }

    /** Tellen de termijnen op tot honderd procent? Zo niet, mist er geld. */
    public function termsCoverage(): float
    {
        return round((float) $this->terms->where('fixed', false)->sum('percentage'), 2);
    }
}
