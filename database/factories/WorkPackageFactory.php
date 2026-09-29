<?php

namespace Database\Factories;

use App\Enums\PhaseStatus;
use App\Models\Project;
use App\Models\WorkPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkPackage>
 */
class WorkPackageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'project_phase_id' => function (array $attributes) {
                return Project::find($attributes['project_id'])
                    ->phases()
                    ->firstWhere('name', 'Uitvoering')
                    ->id;
            },
            'name' => fake()->randomElement(['Elektra begane grond', 'Leidingwerk badkamer', 'Tegelwerk vloer', 'Stucwerk plafond']),
            'status' => PhaseStatus::Bezig,
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
