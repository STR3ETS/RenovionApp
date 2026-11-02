<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Een servicemelding na de oplevering. De vraag die er altijd bij hoort is of
 * het onder garantie valt: zo ja, dan is het voor onze rekening, zo nee, dan
 * moet dat eerst met de klant besproken worden voordat er iemand langskomt.
 */
#[Fillable([
    'number', 'project_id', 'customer_id', 'title', 'description', 'status', 'priority',
    'warranty', 'warranty_note', 'assigned_to', 'work_order_id', 'planned_on',
    'resolved_at', 'resolution', 'reported_by', 'reported_via',
])]
class ServiceTicket extends Model
{
    public const STATUS_NEW = 'nieuw';

    public const STATUS_PLANNED = 'gepland';

    public const STATUS_IN_PROGRESS = 'bezig';

    public const STATUS_RESOLVED = 'afgehandeld';

    public const STATUS_DECLINED = 'afgewezen';

    /** @var array<string, string> */
    public const STATUSES = [
        self::STATUS_NEW => 'Nieuw',
        self::STATUS_PLANNED => 'Gepland',
        self::STATUS_IN_PROGRESS => 'Bezig',
        self::STATUS_RESOLVED => 'Afgehandeld',
        self::STATUS_DECLINED => 'Afgewezen',
    ];

    /** @var array<string, string> */
    public const PRIORITIES = [
        'spoed' => 'Spoed, lekkage of onveilig',
        'hoog' => 'Hoog',
        'normaal' => 'Normaal',
        'laag' => 'Laag',
    ];

    /** @var array<string, string> */
    public const CHANNELS = [
        'telefoon' => 'Telefonisch',
        'mail' => 'E-mail',
        'portaal' => 'Klantportaal',
        'whatsapp' => 'WhatsApp',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'warranty' => 'boolean',
            'planned_on' => 'date',
            'resolved_at' => 'datetime',
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

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class)->latest();
    }

    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereNotIn('status', [self::STATUS_RESOLVED, self::STATUS_DECLINED]);
    }

    public static function nextNumber(): string
    {
        $year = now()->year;
        $last = self::where('number', 'like', "SRV-{$year}-%")
            ->orderByDesc('number')
            ->value('number');

        $sequence = $last === null ? 0 : (int) str($last)->afterLast('-')->value();

        return sprintf('SRV-%d-%04d', $year, $sequence + 1);
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, [self::STATUS_RESOLVED, self::STATUS_DECLINED], true);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function badgeClasses(): string
    {
        return match ($this->status) {
            self::STATUS_NEW => 'bg-amber-500/15 text-amber-300',
            self::STATUS_PLANNED => 'bg-lead-500/15 text-lead-300',
            self::STATUS_IN_PROGRESS => 'bg-brand-500/15 text-brand-300',
            self::STATUS_RESOLVED => 'bg-green-500/15 text-green-300',
            default => 'bg-white/10 text-slate-400',
        };
    }

    public function priorityLabel(): string
    {
        return self::PRIORITIES[$this->priority] ?? $this->priority;
    }

    /**
     * Valt dit nog binnen de garantietermijn van het project? Dat rekenen we uit
     * de opleverdatum, zodat niemand het hoeft op te zoeken.
     */
    public function withinWarrantyPeriod(): ?bool
    {
        $delivered = $this->project?->end_date_actual;

        if ($delivered === null) {
            return null;
        }

        return $delivered->copy()
            ->addMonths($this->project->warranty_months ?? 12)
            ->greaterThanOrEqualTo($this->created_at ?? now());
    }

    /** Hoelang de klant al wacht. */
    public function daysOpen(): int
    {
        return (int) ($this->created_at?->diffInDays($this->resolved_at ?? now()) ?? 0);
    }
}
