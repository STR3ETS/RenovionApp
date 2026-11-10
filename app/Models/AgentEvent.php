<?php

namespace App\Models;

use App\Support\AgentRegistry;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een gebeurtenis in de audit van de agents: wie deed wat, waarover en wanneer.
 *
 * Dit staat los van de gewone audit omdat er één vraag is die je hier altijd
 * moet kunnen beantwoorden: heeft een mens dit besloten, of een agent? Daarom
 * staat de actor er voluit in, als "agent:milo" of "user:7".
 */
#[Fillable([
    'type', 'actor', 'agent_run_id', 'project_id', 'subject_type', 'subject_id',
    'payload', 'occurred_at',
])]
class AgentEvent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
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

    /**
     * Een gebeurtenis vastleggen. De actor is een agent of een mens, nooit iets
     * ertussenin.
     */
    public static function emit(
        string $type,
        string $actor,
        ?array $payload = null,
        ?AgentRun $run = null,
        ?int $projectId = null,
        ?Model $subject = null,
    ): self {
        return self::create([
            'type' => $type,
            'actor' => $actor,
            'agent_run_id' => $run?->id,
            'project_id' => $projectId ?? $run?->project_id,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'payload' => $payload,
            'occurred_at' => now(),
        ]);
    }

    public function isAgent(): bool
    {
        return str_starts_with($this->actor, 'agent:');
    }

    /** De naam van wie het deed, leesbaar in plaats van als code. */
    public function actorName(): string
    {
        if ($this->isAgent()) {
            $id = str($this->actor)->after('agent:')->value();

            return AgentRegistry::agent($id)['name'];
        }

        $id = (int) str($this->actor)->after('user:')->value();

        return User::find($id)?->name ?? 'Onbekend';
    }
}
