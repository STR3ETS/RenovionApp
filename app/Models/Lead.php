<?php

namespace App\Models;

use App\Enums\ActionSource;
use App\Enums\CallOutcome;
use App\Enums\LeadQualification;
use App\Enums\LeadStatus;
use App\Enums\NextActionType;
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
use Illuminate\Support\Carbon;

#[Fillable([
    'customer_id', 'service', 'description', 'source', 'status', 'value',
    'qualification', 'desired_start',
    'assigned_to', 'last_contact_at', 'next_action', 'next_action_type', 'next_action_at', 'missed_calls',
    'phone_requested_at', 'lost_reason', 'position', 'campaign',
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
            'next_action_type' => NextActionType::class,
            'missed_calls' => 'integer',
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

    public function intake(): HasOne
    {
        return $this->hasOne(Intake::class);
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

    /** Na zoveel keer achter elkaar geen gehoor telt de aanvraag als niet bereikt. */
    public const UNREACHED_AFTER = 2;

    public function isUnreached(): bool
    {
        return $this->missed_calls >= self::UNREACHED_AFTER;
    }

    /**
     * Wanneer we het opnieuw proberen na geen gehoor: over twee uur, of de
     * volgende werkdag om tien uur. Valt "over twee uur" buiten de werkdag, dan
     * wordt het ook de volgende werkdag.
     */
    public static function retryMoment(string $choice, ?Carbon $now = null): Carbon
    {
        $now = ($now ?? now())->copy();
        $soon = $now->copy()->addHours(2);

        if ($choice === 'plus2' && $soon->isWeekday() && $soon->hour >= 7 && $soon->hour < 19) {
            return $soon->startOfMinute();
        }

        return $now->nextWeekday()->setTime(10, 0);
    }

    /**
     * Een belpoging vastleggen. Elke uitkomst is een contactmoment in het
     * dossier en zet de volgende stap klaar, die vanzelf in de agenda komt.
     * Alleen een echt gesprek of een terugbelverzoek telt als contact.
     */
    public function logCallAttempt(
        CallOutcome $outcome,
        ?string $note = null,
        ?Carbon $nextAt = null,
        ?NextActionType $nextType = null,
        bool $mailSent = false,
        ActionSource $source = ActionSource::Handmatig,
    ): TimelineEvent {
        $missed = $outcome->isMissed() ? $this->missed_calls + 1 : 0;

        $title = match ($outcome) {
            CallOutcome::GeenGehoor => 'Geen gehoor, poging '.$missed,
            CallOutcome::Voicemail => 'Voicemail ingesproken, poging '.$missed.($mailSent ? ', mail gestuurd' : ''),
            CallOutcome::Terugbelverzoek => 'Terugbelverzoek'.($nextAt ? ' voor '.$nextAt->translatedFormat('j M H:i') : ''),
            CallOutcome::Gesproken => 'Gesproken',
        };

        $event = $this->customer->recordEvent(
            TimelineEventType::Telefoon,
            $title.(filled($note) ? ': '.str($note)->limit(140) : ''),
            filled($note) && mb_strlen($note) > 140 ? $note : null,
            $this,
            $source,
            ['uitkomst' => $outcome->value, 'poging' => $missed],
        );

        if ($outcome->isMissed()) {
            $nextAt ??= self::retryMoment('morgen');
            $nextType = NextActionType::Bellen;
        } elseif ($outcome === CallOutcome::Terugbelverzoek) {
            $nextType = NextActionType::Terugbellen;
        }

        $contact = ! $outcome->isMissed();

        $this->update([
            'missed_calls' => $missed,
            'last_contact_at' => $contact ? now() : $this->last_contact_at,
            'next_action_type' => $nextAt ? ($nextType ?? NextActionType::Bellen) : null,
            'next_action' => $nextAt ? ($nextType ?? NextActionType::Bellen)->label() : null,
            'next_action_at' => $nextAt,
            'status' => $contact && $this->status === LeadStatus::Nieuw ? LeadStatus::Contact : $this->status,
        ]);

        AuditLog::record($this, 'belpoging', [], ['uitkomst' => $outcome->value, 'poging' => $missed], $source);
        AttentionService::forgetCount();

        return $event;
    }

    /**
     * Wat Nova aanraadt als bellen niet lukt: na twee keer geen gehoor een
     * ander kanaal, afhankelijk van wat we van de klant hebben.
     */
    public function callAdvice(): ?string
    {
        if (! $this->isUnreached()) {
            return null;
        }

        $channel = match (true) {
            filled($this->customer->phone) && filled($this->customer->email) => 'Stuur een WhatsApp of een mail',
            filled($this->customer->email) => 'Stuur een mail',
            filled($this->customer->phone) => 'Stuur een WhatsApp',
            default => 'Zoek een ander nummer of adres',
        };

        return $this->missed_calls.' keer niet bereikt. '.$channel.' in plaats van nog een keer te bellen.';
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
            'missed_calls' => 0,
            'next_action' => $nextAction,
            'next_action_type' => $nextActionAt ? $this->next_action_type : null,
            'next_action_at' => $nextActionAt,
            'status' => $this->status === LeadStatus::Nieuw ? LeadStatus::Contact : $this->status,
        ]);

        AuditLog::record($this, 'contactmoment', [], ['type' => $type->value], $source);
        AttentionService::forgetCount();

        return $event;
    }
}
