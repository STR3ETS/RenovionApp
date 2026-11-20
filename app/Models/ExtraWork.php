<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Meerwerk als eigen opdracht onder het projectnummer: PD-0012-MW01. De klant
 * geeft online akkoord, daarna pas voeren we het uit en factureren we het
 * apart. De hoofdfactuur wacht er nooit op.
 */
#[Fillable([
    'project_id', 'number', 'description', 'details', 'amount', 'status', 'public_token',
    'sent_at', 'approved_at', 'approved_name', 'approved_ip', 'invoice_id', 'created_by',
])]
class ExtraWork extends Model
{
    public const STATUS_DRAFT = 'concept';

    public const STATUS_SENT = 'verzonden';

    public const STATUS_APPROVED = 'akkoord';

    public const STATUS_INVOICED = 'gefactureerd';

    /** @var array<string, string> */
    public const STATUSES = [
        self::STATUS_DRAFT => 'Concept',
        self::STATUS_SENT => 'Wacht op akkoord',
        self::STATUS_APPROVED => 'Akkoord',
        self::STATUS_INVOICED => 'Gefactureerd',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'sent_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Het volgende nummer onder dit project: MW01, MW02 en zo verder. */
    public static function nextNumberFor(Project $project): string
    {
        $prefix = $project->number().'-MW';
        $last = self::where('project_id', $project->id)->orderByDesc('number')->value('number');
        $sequence = $last === null ? 0 : (int) str($last)->afterLast('MW')->value();

        return sprintf('%s%02d', $prefix, $sequence + 1);
    }

    public static function newToken(): string
    {
        return Str::random(48);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function badgeClasses(): string
    {
        return match ($this->status) {
            self::STATUS_SENT => 'bg-amber-500/15 text-amber-300',
            self::STATUS_APPROVED => 'bg-green-500/15 text-green-300',
            self::STATUS_INVOICED => 'bg-lead-500/15 text-lead-300',
            default => 'bg-white/10 text-slate-300',
        };
    }

    public function publicUrl(): string
    {
        return route('extra-works.public', $this->public_token);
    }

    /** Pas uitvoeren en factureren na akkoord van de klant. */
    public function isApproved(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_INVOICED], true);
    }

    public function awaitsCustomer(): bool
    {
        return $this->status === self::STATUS_SENT;
    }
}
