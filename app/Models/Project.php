<?php

namespace App\Models;

use App\Enums\PhaseStatus;
use App\Enums\ProjectStatus;
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
    'customer_id', 'lead_id', 'quote_id', 'name', 'address', 'city', 'status',
    'value', 'deposit_amount', 'deposit_received_at', 'paid_amount', 'next_payment_due_at',
    'start_date', 'end_date_expected', 'end_date_actual', 'project_leader_id', 'progress', 'notes',
    'cover_photo_path',
])]
class Project extends Model
{
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
