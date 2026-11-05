<?php

namespace App\Actions;

use App\Enums\ActionSource;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

/**
 * Een taak aanmaken, vanaf de website (TaskController) of de app (Api\App\TaskController): een uitvoerder
 * maakt alleen taken voor zichzelf, en de klant komt mee van het project of de aanvraag.
 */
class CreateTask
{
    /** @param array<string, mixed> $validated */
    public function handle(array $validated, User $user): Task
    {
        if ($user->cannot('manage-crm')) {
            $validated['owner_id'] = $user->id;
        }

        if (empty($validated['customer_id'])) {
            $validated['customer_id'] = match (true) {
                ! empty($validated['project_id']) => Project::find($validated['project_id'])?->customer_id,
                ! empty($validated['lead_id']) => Lead::find($validated['lead_id'])?->customer_id,
                default => null,
            };
        }

        return Task::create([
            ...$validated,
            'owner_id' => $validated['owner_id'] ?? $user->id,
            'source' => ActionSource::Handmatig,
        ]);
    }
}
