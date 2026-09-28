<?php

namespace App\Models;

use App\Enums\QuoteStatus;
use App\Enums\TimelineEventType;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

#[Fillable([
    'number', 'version', 'customer_id', 'lead_id', 'calculation_id', 'project_id', 'status',
    'subtotal', 'vat_amount', 'total', 'sent_at', 'viewed_at',
    'viewed_count', 'accepted_at', 'rejected_at', 'valid_until', 'notes', 'blocks',
])]
class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory;

    /**
     * Spiegelt de databasedefault, zodat ook ongepersisteerde instanties
     * een versienummer hebben.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'version' => 1,
    ];

    /**
     * Elke offerte krijgt direct een klantlink-token (briefing §6).
     */
    protected static function booted(): void
    {
        static::creating(function (Quote $quote) {
            $quote->public_token ??= Str::random(40);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'blocks' => 'array',
            'subtotal' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'sent_at' => 'datetime',
            'viewed_at' => 'datetime',
            'signed_at' => 'datetime',
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

    public function calculation(): BelongsTo
    {
        return $this->belongsTo(Calculation::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(QuoteVersion::class)->orderByDesc('version');
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
        $prefix = "REN-{$year}-";

        $last = self::where('number', 'like', "{$prefix}%")
            ->orderByDesc('number')
            ->value('number');

        $sequence = $last === null ? 1 : ((int) str_replace($prefix, '', $last)) + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Alleen de ingeschakelde blokken, in de vaste volgorde.
     *
     * @return list<array{key: string, title: string, body: string, enabled: bool}>
     */
    public function enabledBlocks(): array
    {
        return array_values(array_filter($this->blocks ?? [], fn (array $block) => $block['enabled'] ?? true));
    }

    public function publicUrl(): string
    {
        return route('quotes.public', $this->public_token);
    }

    public function isExpired(): bool
    {
        return $this->valid_until !== null && $this->valid_until->isPast();
    }

    /**
     * Bevries de huidige inhoud als verzonden versie (briefing §6:
     * een offerte moet exact kunnen aantonen wat er per versie stond).
     */
    public function freezeVersion(?string $note = null): QuoteVersion
    {
        $this->loadMissing('lines');

        return $this->versions()->updateOrCreate(
            ['version' => $this->version],
            [
                'note' => $note,
                'created_by' => Auth::id(),
                'snapshot' => [
                    'blocks' => $this->blocks,
                    'lines' => $this->lines
                        ->map(fn (QuoteLine $line) => $line->only(['description', 'quantity', 'unit', 'unit_price', 'vat_rate', 'total', 'is_estimate', 'position']))
                        ->all(),
                    'subtotal' => (string) $this->subtotal,
                    'vat_amount' => (string) $this->vat_amount,
                    'total' => (string) $this->total,
                    'valid_until' => $this->valid_until?->toDateString(),
                ],
            ],
        );
    }

    /**
     * Start een nieuwe versie (v2, v3, …) met wijzigingslog. De offerte gaat
     * terug naar concept; na akkoord betekent dit opnieuw laten tekenen.
     */
    public function startNewVersion(string $note): void
    {
        $this->freezeVersion();

        $this->forceFill([
            'version' => $this->version + 1,
            'status' => QuoteStatus::Concept,
            'sent_at' => null,
            'viewed_at' => null,
            'accepted_at' => null,
            'rejected_at' => null,
            'signed_name' => null,
            'signed_at' => null,
            'signed_ip' => null,
            'change_request' => null,
        ])->save();

        AuditLog::record($this, 'nieuwe_versie', ['versie' => $this->version - 1], ['versie' => $this->version, 'toelichting' => $note]);
        $this->customer->recordEvent(TimelineEventType::Offerte, "Offerte {$this->number} — nieuwe versie v{$this->version}", $note, $this);
    }
}
