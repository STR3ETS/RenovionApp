<?php

namespace App\Models;

use App\Enums\PhaseStatus;
use App\Enums\ProjectStatus;
use App\Enums\QuoteStatus;
use App\Enums\UserRole;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'customer_id', 'lead_id', 'quote_id', 'name', 'type', 'address', 'city', 'status',
    'value', 'deposit_amount', 'deposit_received_at', 'paid_amount', 'next_payment_due_at',
    'retention_type', 'retention_amount', 'retention_release_on', 'retention_released_at',
    'warranty_months',
    'start_date', 'end_date_expected', 'end_date_actual', 'project_leader_id', 'progress', 'notes',
    'cover_photo_path',
    'contract_form', 'regie_hourly_rate', 'regie_day_rate', 'order_confirmed_at',
])]
class Project extends Model
{
    public const FORM_FIXED = 'aangenomen';

    public const FORM_REGIE = 'regie';

    /** @var array<string, string> */
    public const FORMS = [
        self::FORM_FIXED => 'Aangenomen werk',
        self::FORM_REGIE => 'Regie',
    ];

    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * Elk project krijgt bij aanmaak de vaste fasen 0–8 (briefing §7),
     * ongeacht waar het ontstaat (handmatig, offerte-akkoord of Nova).
     */
    protected static function booted(): void
    {
        static::created(function (Project $project) {
            $project->phases()->createMany(collect(ProjectPhase::NAMES)->map(fn (string $name, int $position) => [
                'position' => $position,
                'name' => $name,
                'status' => $position === 0 ? PhaseStatus::Bezig : PhaseStatus::NietGestart,
            ])->all());

            // Elk project krijgt een eigen chatkanaal (briefing §11).
            ChatChannel::create([
                'type' => 'project',
                'name' => $project->name,
                'project_id' => $project->id,
            ]);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'value' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'deposit_received_at' => 'datetime',
            'paid_amount' => 'decimal:2',
            'next_payment_due_at' => 'date',
            'start_date' => 'date',
            'end_date_expected' => 'date',
            'end_date_actual' => 'date',
            'retention_amount' => 'decimal:2',
            'retention_release_on' => 'date',
            'retention_released_at' => 'datetime',
            'regie_hourly_rate' => 'decimal:2',
            'regie_day_rate' => 'decimal:2',
            'hours_signed_at' => 'datetime',
            'hours_signed_total' => 'decimal:2',
            'order_confirmed_at' => 'datetime',
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

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /** Alle offertes die bij dit project horen, ook latere versies en aanvullingen. */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class)->orderBy('date')->orderBy('id');
    }

    public function regieMaterials(): HasMany
    {
        return $this->hasMany(RegieMaterial::class)->orderBy('id');
    }

    public function extraWorks(): HasMany
    {
        return $this->hasMany(ExtraWork::class)->orderBy('number');
    }

    public function siteRegistration(): HasOne
    {
        return $this->hasOne(SiteRegistration::class);
    }

    public function agreements(): HasMany
    {
        return $this->hasMany(ProjectAgreement::class);
    }

    public function hoursSigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hours_signed_by');
    }

    /** Het projectnummer zoals het op alle stukken staat. */
    public function number(): string
    {
        return 'PD-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    /** Regie: we rekenen uren en materiaal af in plaats van een vaste prijs. */
    public function isRegie(): bool
    {
        return $this->contract_form === self::FORM_REGIE;
    }

    /**
     * Zonder opdracht geen uitvoering. Een project is in opdracht als een
     * offerte is getekend, of als het kantoor heeft vastgelegd dat de opdracht
     * buiten het systeem om is gegeven. Een project zonder enige offerte in
     * het systeem is zo'n opdracht: het kantoor maakte het zelf aan.
     */
    public function hasOrder(): bool
    {
        if ($this->order_confirmed_at !== null) {
            return true;
        }

        $quotes = $this->quotes()->get()
            ->when($this->quote !== null, fn ($quotes) => $quotes->push($this->quote))
            ->unique('id');

        return $quotes->isEmpty()
            || $quotes->contains(fn (Quote $quote) => $quote->status === QuoteStatus::Akkoord);
    }

    public function projectLeader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_leader_id');
    }

    public function craftsmen(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function phases(): HasMany
    {
        return $this->hasMany(ProjectPhase::class)->orderBy('position');
    }

    /**
     * De fase waar het project nu in zit: de eerste die nog niet gereed is.
     */
    public function currentPhase(): ?ProjectPhase
    {
        return $this->phases->firstWhere(fn (ProjectPhase $phase) => $phase->status !== PhaseStatus::Gereed)
            ?? $this->phases->last();
    }

    public function workPackages(): HasMany
    {
        return $this->hasMany(WorkPackage::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class)->latest();
    }

    public function chatChannel(): HasOne
    {
        return $this->hasOne(ChatChannel::class);
    }

    public function deliveryReport(): HasOne
    {
        return $this->hasOne(DeliveryReport::class);
    }

    /**
     * Omslag: handmatig geüpload, anders het meest recente klantzichtbare foto-bewijs.
     */
    public function coverPhotoPath(): ?string
    {
        return $this->cover_photo_path
            ?? $this->photos()->where('client_visible', true)->value('path');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function snags(): HasMany
    {
        return $this->hasMany(SnagItem::class);
    }

    public function serviceTickets(): HasMany
    {
        return $this->hasMany(ServiceTicket::class)->latest();
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class)->latest('date');
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class)->latest('id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->latest('date');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class)->latest('id');
    }

    public function qualityReview(): HasOne
    {
        return $this->hasOne(QualityReview::class);
    }

    /**
     * Uitvoerders zien alleen projecten waar ze op ingedeeld zijn (briefing §15).
     */
    public function isAccessibleBy(User $user): bool
    {
        return $user->can('manage-crm')
            || ($user->role !== UserRole::Klant && $this->craftsmen()->whereKey($user->id)->exists());
    }

    /**
     * Klantportaal (briefing §10): een klant ziet uitsluitend projecten
     * van het eigen klantdossier, en dan alleen klantzichtbare content.
     */
    public function isViewableByClient(User $user): bool
    {
        return $user->role === UserRole::Klant
            && $user->customer_id !== null
            && $user->customer_id === $this->customer_id;
    }

    /**
     * Voortgang% wordt berekend uit de fasen en hun werkpakketten (briefing §7),
     * zodat het dashboard en klantportaal altijd de werkelijke stand tonen.
     */
    public function syncProgress(): void
    {
        $phases = $this->phases()->with('workPackages')->get();

        if ($phases->isEmpty()) {
            return;
        }

        $this->forceFill([
            'progress' => (int) round($phases->avg(fn (ProjectPhase $phase) => $phase->completionFraction()) * 100),
        ])->save();
    }

    public function scheduleEntries(): HasMany
    {
        return $this->hasMany(ScheduleEntry::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('status', '!=', ProjectStatus::Afgerond);
    }

    public function outstandingAmount(): float
    {
        return max(0, (float) $this->value - (float) $this->paid_amount);
    }

    public function depositOutstanding(): bool
    {
        return $this->deposit_amount !== null
            && (float) $this->deposit_amount > 0
            && $this->deposit_received_at === null;
    }

    /**
     * Project loopt uit: verwachte einddatum is verstreken terwijl het nog niet is afgerond.
     */
    public function isOverdue(): bool
    {
        return $this->end_date_expected !== null
            && $this->end_date_expected->isPast()
            && $this->status !== ProjectStatus::Afgerond;
    }
}
