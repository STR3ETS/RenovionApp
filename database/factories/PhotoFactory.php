<?php

namespace Database\Factories;

use App\Models\Photo;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Photo>
 */
class PhotoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'path' => 'project-photos/'.fake()->uuid().'.jpg',
            'caption' => fake()->optional()->words(3, true),
            'client_visible' => true,
        ];
    }

    public function intern(): static
    {
        return $this->state(['client_visible' => false]);
    }
}
