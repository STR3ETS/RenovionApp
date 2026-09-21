<?php

namespace Database\Factories;

use App\Enums\ActionSource;
use App\Enums\LeadStatus;
use App\Models\Customer;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'service' => fake()->randomElement([
                'Complete renovatie', 'Badkamerrenovatie', 'Stucwerk',
                'Schilderwerk', 'Tegelwerk', 'Toiletrenovatie',
            ]),
            'description' => fake()->sentence(10),
            'source' => ActionSource::Website,
            'status' => LeadStatus::Nieuw,
            'position' => 0,
        ];
    }

    public function status(LeadStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
