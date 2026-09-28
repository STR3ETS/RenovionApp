<?php

namespace App\Models;

use App\Enums\ActionSource;
use App\Enums\LeadQualification;
use App\Enums\LeadStatus;
use App\Enums\TimelineEventType;
use App\Services\AttentionService;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'customer_id', 'service', 'description', 'source', 'status', 'value',
    'qualification', 'desired_start',
    'assigned_to', 'last_contact_at', 'next_action', 'next_action_at',
    'phone_requested_at', 'lost_reason', 'position',
])]
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'qualification' => LeadQualification::class,
            'source' => ActionSource::class,
            'value' => 'decimal:2',
            'last_contact_at' => 'datetime',
            'next_action_at' => 'datetime',
            'phone_requested_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function calculations(): HasMany
    {
        return $this->hasMany(Calculation::class);
    }

    public function project(): HasOne
    {
        return $this->hasOne(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    #[Scope]
    protected function open(Builder $query): Builder
    {
        return $query->whereNotIn('status', [LeadStatus::Project, LeadStatus::Verloren]);
    }

    public function missingPhone(): bool
    {
        return blank($this->customer->phone);
    }

    public function needsFollowUpToday(): bool
    {
        return $this->next_action_at !== null && $this->next_action_at->isToday();
    }

    /**
     * Stille aanvraag: open, geen volgende actie gepland en al minstens
     * X dagen geen contact (briefing §4: Nova bewaakt contactmomenten).
     */
    public function isSilent(int $days = 3): bool
    {
        return $this->status->isOpen()
            && $this->status !== LeadStatus::OnHold
            && $this->next_action_at === null
            && ($this->last_contact_at ?? $this->created_at)->lte(now()->subDays($days));
    }

    /**
     * Leg een contactmoment vast: timeline-event, laatste contact bijwerken,
     * volgende actie (her)plannen en een nieuwe aanvraag doorschuiven naar
     * status Contact. Gebruikt door het formulier én door Nova.
     */
    public function logContact(
        TimelineEventType $type,
        string $summary,
        ?string $nextAction = null,
        ?string $nextActionAt = null,
        ActionSource $source = ActionSource::Handmatig,
    ): TimelineEvent {
        $event = $this->customer->recordEvent(
            $type,
            $type->label().': '.str($summary)->limit(160),
            mb_strlen($summary) > 160 ? $summary : null,
            $this,
            $source,
        );

        $this->update([
            'last_contact_at' => now(),
            'next_action' => $nextAction,
            'next_action_at' => $nextActionAt,
            'status' => $this->status === LeadStatus::Nieuw ? LeadStatus::Contact : $this->status,
        ]);

        AuditLog::record($this, 'contactmoment', [], ['type' => $type->value], $source);
        AttentionService::forgetCount();

        return $event;
    }
}
