<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
            default => 'bg-ink/10 text-ink2',
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

    /**
     * Het beste bod: de hoogste marge bij een complete scope en een partij met
     * een goedgekeurd dossier. Weten we de verkoop nog niet, dan is de laagste
     * prijs het beste. Alle biedingen blijven zichtbaar, deze staat bovenaan.
     */
    public function bestComparableBid(): ?RfqBid
    {
        $candidates = $this->bids->filter(
            fn (RfqBid $bid) => $bid->isComplete() && $bid->subcontractor->mayBeInvited(),
        );

        if ($candidates->contains(fn (RfqBid $bid) => $bid->marginPct() !== null)) {
            return $candidates
                ->sortBy([
                    fn (RfqBid $a, RfqBid $b) => ($b->marginPct() ?? -INF) <=> ($a->marginPct() ?? -INF),
                    fn (RfqBid $a, RfqBid $b) => (float) $a->price <=> (float) $b->price,
                ])
                ->first();
        }

        return $candidates->sortBy('price')->first();
    }

    public function purchaseOrder(): HasOne
    {
        return $this->hasOne(PurchaseOrder::class);
    }

    /**
     * De tekst voor de uitvraag via WhatsApp: kort, met wat de partij nodig
     * heeft om te bieden.
     */
    public function whatsappText(): string
    {
        return collect([
            'Prijsaanvraag '.$this->number.' van Renovion Groep: '.$this->title.'.',
            $this->project?->city ? 'Locatie: '.$this->project->city.'.' : null,
            $this->scope ? 'Werk: '.str($this->scope)->squish()->limit(300).'.' : null,
            $this->starts_on ? 'Gewenste start: '.$this->starts_on->translatedFormat('j F').'.' : null,
            $this->deadline ? 'Graag je prijs voor '.$this->deadline->translatedFormat('j F').'.' : 'Graag je prijs zo snel mogelijk.',
        ])->filter()->join(' ');
    }

    /** Een uitgezette aanvraag waarvan de deadline voorbij is, vraagt actie. */
    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_SENT
            && $this->deadline !== null
            && $this->deadline->isPast();
    }
}
