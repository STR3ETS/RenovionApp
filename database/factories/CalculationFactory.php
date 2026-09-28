<?php

namespace Database\Factories;

use App\Enums\CalculationStatus;
use App\Models\Calculation;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Calculation>
 */
class CalculationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'title' => 'Calculatie '.fake()->words(2, true),
            'status' => CalculationStatus::Concept,
            'risk_pct' => 5,
            'margin_pct' => 15,
            'vat_pct' => 21,
        ];
    }

    public function definitief(): static
    {
        return $this->state(['status' => CalculationStatus::Definitief]);
    }
}
