<?php

namespace App\Models;

use App\Support\AgentRegistry;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een goedkeuringsverzoek van een agent aan een mens.
 *
 * De vingerafdruk is het hart hiervan: hij legt vast waar het voorstel over
 * ging. Verandert het onderwerp daarna, dan vervalt de goedkeuring, want dan
 * heeft de mens iets anders goedgekeurd dan er nu ligt. Dat is precies het
 * verschil tussen een vinkje en een besluit.
 */
#[Fillable([
    'reference', 'agent_run_id', 'project_id', 'agent', 'skill', 'action', 'summary',
    'payload', 'fingerprint', 'status', 'approver_role', 'decided_by', 'decided_at', 'reason',
])]
class AgentApproval extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_APPROVED = 'goedgekeurd';

    public const STATUS_DECLINED = 'afgewezen';

    public const STATUS_STALE = 'vervallen';

    /** @var array<string, string> */
    public const STATUSES = [
        self::STATUS_OPEN => 'Open',
        self::STATUS_APPROVED => 'Goedgekeurd',
        self::STATUS_DECLINED => 'Afgewezen',
        self::STATUS_STALE => 'Vervallen, het voorstel is gewijzigd',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'decided_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class, 'agent_run_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    #[Scope]
    protected function open(Builder $query): void
    {
        $query->where('status', self::STATUS_OPEN);
    }

    public static function nextReference(): string
    {
        $last = self::orderByDesc('id')->value('reference');
        $sequence = $last === null ? 0 : (int) str($last)->afterLast('_')->value();

        return sprintf('apr_%04d', $sequence + 1);
    }

    /**
     * De vingerafdruk van een voorstel. Dezelfde inhoud levert dezelfde
     * vingerafdruk, en elke wijziging een andere.
     */
    public static function fingerprint(mixed $subject): string
    {
        return hash('sha256', json_encode($subject, JSON_THROW_ON_ERROR));
    }

    /**
     * Klopt de goedkeuring nog met wat er nu ligt? Zo niet, dan is hij vervallen
     * en moet er opnieuw worden besloten.
     */
    public function matches(mixed $subject): bool
    {
        return $this->fingerprint === self::fingerprint($subject);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function badgeClasses(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'bg-green-500/15 text-green-300',
            self::STATUS_DECLINED => 'bg-red-500/15 text-red-300',
            self::STATUS_STALE => 'bg-white/10 text-slate-400',
            default => 'bg-amber-500/15 text-amber-300',
        };
    }

    /**
     * @return array{name: string, field: string, owner: string, task: string, colour: string}
     */
    public function agentMeta(): array
    {
        return AgentRegistry::agent($this->agent);
    }

    /** Hoelang dit verzoek al op een besluit wacht. */
    public function waitingDays(): int
    {
        return (int) $this->created_at->diffInDays($this->decided_at ?? now());
    }
}
