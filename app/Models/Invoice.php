<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Een uitgaande factuur. Het bedrag staat exclusief btw; de btw volgt uit het
 * percentage, zodat een tariefwijziging oude facturen niet verandert.
 */
#[Fillable([
    'number', 'project_id', 'customer_id', 'budget_term_id', 'type', 'description',
    'date', 'due_date', 'amount', 'vat_pct', 'retention', 'status',
    'sent_at', 'paid_at', 'paid_amount', 'notes',
])]
class Invoice extends Model
{
    /** De betaaltermijn die Renovion aanhoudt. */
    public const PAYMENT_DAYS = 14;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => InvoiceType::class,
            'status' => InvoiceStatus::class,
            'date' => 'date',
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'vat_pct' => 'decimal:2',
            'retention' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'sent_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(BudgetTerm::class, 'budget_term_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('position')->orderBy('id');
    }

    #[Scope]
    protected function outstanding(Builder $query): void
    {
        $query->where('status', InvoiceStatus::Verzonden);
    }

    #[Scope]
    protected function overdue(Builder $query): void
    {
        $query->where('status', InvoiceStatus::Verzonden)->whereDate('due_date', '<', today());
    }

    #[Scope]
    protected function revenue(Builder $query): void
    {
        $query->where('status', '!=', InvoiceStatus::Concept);
    }

    /**
     * Doorlopende nummering per jaar: FAC-2026-0001.
     */
    public static function nextNumber(): string
    {
        $year = now()->year;
        $last = self::where('number', 'like', "FAC-{$year}-%")
            ->orderByDesc('number')
            ->value('number');

        $sequence = $last === null ? 0 : (int) str($last)->afterLast('-')->value();

        return sprintf('FAC-%d-%04d', $year, $sequence + 1);
    }

    public function vatAmount(): float
    {
        return round((float) $this->amount * (float) $this->vat_pct / 100, 2);
    }

    /** Wat de klant overmaakt: bedrag plus btw, minus wat hij mag achterhouden. */
    public function total(): float
    {
        return round((float) $this->amount + $this->vatAmount() - (float) $this->retention, 2);
    }

    public function signedAmount(): float
    {
        return round((float) $this->amount * $this->type->sign(), 2);
    }

    public function isOverdue(): bool
    {
        return $this->status->isOutstanding() && $this->due_date->isPast();
    }

    public function daysOverdue(): int
    {
        return $this->isOverdue() ? (int) $this->due_date->diffInDays(today()) : 0;
    }

    /** Het deel dat nog niet is binnengekomen. */
    public function openAmount(): float
    {
        return round(max(0, $this->total() - (float) $this->paid_amount), 2);
    }
}
