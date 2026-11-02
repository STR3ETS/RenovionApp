<?php

namespace App\Models;

use App\Support\AgentRegistry;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Een uitvoering van een skill door een agent. Wachten is hier een echte
 * toestand: een agent die op een goedkeuring, de klant, bewijs of een
 * leverancier wacht, is niet mislukt maar niet klaar.
 */
#[Fillable([
    'reference', 'agent', 'skill', 'project_id', 'status', 'trigger',
    'started_at', 'finished_at', 'cost', 'steps', 'note', 'started_by',
])]
class AgentRun extends Model
{
    public const STATUS_QUEUED = 'gepland';

    public const STATUS_RUNNING = 'bezig';

    public const STATUS_WAITING_APPROVAL = 'wacht_op_goedkeuring';

    public const STATUS_WAITING_CUSTOMER = 'wacht_op_klant';

    public const STATUS_WAITING_EVIDENCE = 'wacht_op_bewijs';

    public const STATUS_WAITING_SUPPLIER = 'wacht_op_leverancier';

    public const STATUS_DONE = 'afgerond';

    public const STATUS_FAILED = 'mislukt';

    public const STATUS_CANCELLED = 'afgebroken';

    /** @var array<string, string> */
    public const STATUSES = [
        self::STATUS_QUEUED => 'Gepland',
        self::STATUS_RUNNING => 'Bezig',
        self::STATUS_WAITING_APPROVAL => 'Wacht op goedkeuring',
        self::STATUS_WAITING_CUSTOMER => 'Wacht op de klant',
        self::STATUS_WAITING_EVIDENCE => 'Wacht op bewijs',
        self::STATUS_WAITING_SUPPLIER => 'Wacht op de leverancier',
        self::STATUS_DONE => 'Afgerond',
        self::STATUS_FAILED => 'Mislukt',
        self::STATUS_CANCELLED => 'Afgebroken',
    ];

    /** Statussen waarbij de uitvoering nog loopt of ergens op wacht. */
    public const ACTIVE = [
        self::STATUS_QUEUED,
        self::STATUS_RUNNING,
        self::STATUS_WAITING_APPROVAL,
        self::STATUS_WAITING_CUSTOMER,
        self::STATUS_WAITING_EVIDENCE,
        self::STATUS_WAITING_SUPPLIER,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'cost' => 'decimal:4',
            'steps' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(AgentApproval::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AgentEvent::class)->orderBy('occurred_at');
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereIn('status', self::ACTIVE);
    }

    public static function nextReference(): string
    {
        $last = self::orderByDesc('id')->value('reference');
        $sequence = $last === null ? 0 : (int) str($last)->afterLast('_')->value();

        return sprintf('run_%04d', $sequence + 1);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE, true);
    }

    public function isWaiting(): bool
    {
        return str_starts_with($this->status, 'wacht_op');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function badgeClasses(): string
    {
        return match ($this->status) {
            self::STATUS_DONE => 'bg-green-500/15 text-green-300',
            self::STATUS_RUNNING => 'bg-lead-500/15 text-lead-300',
            self::STATUS_FAILED => 'bg-red-500/15 text-red-300',
            self::STATUS_WAITING_APPROVAL => 'bg-amber-500/15 text-amber-300',
            self::STATUS_CANCELLED => 'bg-white/10 text-slate-400',
            default => $this->isWaiting() ? 'bg-amber-500/15 text-amber-300' : 'bg-white/10 text-slate-300',
        };
    }

    /**
     * @return array{name: string, field: string, owner: string, task: string, colour: string}
     */
    public function agentMeta(): array
    {
        return AgentRegistry::agent($this->agent);
    }

    public function skillName(): string
    {
        return AgentRegistry::skill($this->skill)['name'] ?? $this->skill;
    }

    /** Hoe lang deze uitvoering duurde, of al loopt. */
    public function duration(): ?int
    {
        if ($this->started_at === null) {
            return null;
        }

        return (int) $this->started_at->diffInSeconds($this->finished_at ?? now());
    }

    /**
     * Een stap aan het logboek toevoegen. Zo is achteraf na te lopen wat de agent
     * deed en waarom, in plaats van alleen de uitkomst.
     */
    public function addStep(string $description, ?array $data = null): void
    {
        $steps = $this->steps ?? [];
        $steps[] = [
            'at' => now()->toDateTimeString(),
            'description' => $description,
            'data' => $data,
        ];

        $this->update(['steps' => $steps]);
    }
}
