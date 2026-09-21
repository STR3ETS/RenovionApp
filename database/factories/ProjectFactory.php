<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Customer;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $value = fake()->randomFloat(2, 8000, 60000);

        return [
            'customer_id' => Customer::factory(),
            'name' => fake()->randomElement(['Badkamer', 'Complete renovatie', 'Stucwerk', 'Keuken']).' '.fake()->lastName(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'status' => ProjectStatus::Voorbereiding,
            'value' => $value,
            'deposit_amount' => round($value * 0.3, 2),
            'start_date' => now()->addDays(fake()->numberBetween(3, 21))->toDateString(),
            'end_date_expected' => now()->addDays(fake()->numberBetween(30, 60))->toDateString(),
        ];
    }

    public function inUitvoering(): static
    {
        return $this->state([
            'status' => ProjectStatus::Uitvoering,
            'start_date' => now()->subDays(fake()->numberBetween(3, 14))->toDateString(),
            'deposit_received_at' => now()->subDays(fake()->numberBetween(10, 20)),
            'progress' => fake()->numberBetween(20, 80),
        ]);
    }
}
