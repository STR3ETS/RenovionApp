<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Een prijsaanvraag: een stuk werk dat we uitbesteden, uitgezet bij een paar
 * onderaannemers. Het budget is wat ervoor begroot staat; daartegen beoordeel je
 * de biedingen. Gunnen maakt het bedrag een verplichting op het project.
 */
#[Fillable([
    'number', 'project_id', 'title', 'chapter_code', 'scope', 'budget', 'status',
    'deadline', 'starts_on', 'awarded_to', 'order_number', 'awarded_on', 'created_by',
])]
class Rfq extends Model
{
    public const STATUS_DRAFT = 'concept';

    public const STATUS_SENT = 'uitgezet';

    public const STATUS_AWARDED = 'gegund';

    public const STATUS_DONE = 'opgeleverd';

    /** @var array<string, string> */
    public const STATUSES = [
        self::STATUS_DRAFT => 'Concept',
        self::STATUS_SENT => 'Uitgezet',
        self::STATUS_AWARDED => 'Gegund',
        self::STATUS_DONE => 'Opgeleverd',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'budget' => 'decimal:2',
            'deadline' => 'date',
            'starts_on' => 'date',
            'awarded_on' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function invited(): BelongsToMany
    {
        return $this->belongsToMany(Subcontractor::class, 'rfq_invitations')
            ->withPivot('invited_at')
            ->withTimestamps();
    }

    public function bids(): HasMany
    {
        return $this->hasMany(RfqBid::class)->orderBy('price');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Subcontractor::class, 'awarded_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Gegunde aanvragen zijn verplichtingen die in de prognose meetellen. */
    #[Scope]
    protected function committed(Builder $query): void
    {
        $query->whereIn('status', [self::STATUS_AWARDED, self::STATUS_DONE]);
    }

    public static function nextNumber(): string
    {
        $year = now()->year;
        $last = self::where('number', 'like', "PA-{$year}-%")
            ->orderByDesc('number')
            ->value('number');

        $sequence = $last === null ? 0 : (int) str($last)->afterLast('-')->value();

        return sprintf('PA-%d-%04d', $year, $sequence + 1);
    }

    public static function nextOrderNumber(): string
    {
        $year = now()->year;
        $last = self::whereNotNull('order_number')
            ->where('order_number', 'like', "OA-{$year}-%")
            ->orderByDesc('order_number')
            ->value('order_number');

        $sequence = $last === null ? 0 : (int) str($last)->afterLast('-')->value();

        return sprintf('OA-%d-%04d', $year, $sequence + 1);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function badgeClasses(): string
    {
        return match ($this->status) {
            self::STATUS_SENT => 'bg-lead-500/15 text-lead-300',
            self::STATUS_AWARDED => 'bg-brand-500/15 text-brand-300',
            self::STATUS_DONE => 'bg-green-500/15 text-green-300',
            default => 'bg-white/10 text-slate-300',
        };
    }

    public function winningBid(): ?RfqBid
    {
        return $this->awarded_to === null
            ? null
            : $this->bids->firstWhere('subcontractor_id', $this->awarded_to);
    }

    /** Wat gunnen ons oplevert of kost tegenover de begroting. */
    public function savings(): ?float
    {
        $bid = $this->winningBid();

        return $bid === null ? null : round((float) $this->budget - (float) $bid->price, 2);
    }

    /** De beste bieding die compleet is: alleen die zijn echt vergelijkbaar. */
    public function bestComparableBid(): ?RfqBid
    {
        return $this->bids->filter->isComplete()->sortBy('price')->first();
    }

    /** Een uitgezette aanvraag waarvan de deadline voorbij is, vraagt actie. */
    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_SENT
            && $this->deadline !== null
            && $this->deadline->isPast();
    }
}
