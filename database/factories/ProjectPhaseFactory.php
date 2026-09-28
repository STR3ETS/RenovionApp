<?php

namespace Database\Factories;

use App\Enums\PhaseStatus;
use App\Models\Project;
use App\Models\ProjectPhase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectPhase>
 */
class ProjectPhaseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $position = fake()->numberBetween(0, count(ProjectPhase::NAMES) - 1);

        return [
            'project_id' => Project::factory(),
            'position' => $position,
            'name' => ProjectPhase::NAMES[$position],
            'status' => PhaseStatus::NietGestart,
        ];
    }

    public function gereed(): static
    {
        return $this->state([
            'status' => PhaseStatus::Gereed,
            'completed_at' => now(),
        ]);
    }
}
