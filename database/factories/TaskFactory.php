<?php

namespace Database\Factories;

use App\Enums\ActionSource;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'deadline' => now()->addDays(fake()->numberBetween(0, 10))->toDateString(),
            'priority' => TaskPriority::Normaal,
            'status' => TaskStatus::TeDoen,
            'source' => ActionSource::Handmatig,
        ];
    }

    public function hoog(): static
    {
        return $this->state(['priority' => TaskPriority::Hoog]);
    }

    public function vandaag(): static
    {
        return $this->state(['deadline' => today()->toDateString()]);
    }
}
