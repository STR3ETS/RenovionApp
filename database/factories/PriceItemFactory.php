<?php

namespace Database\Factories;

use App\Enums\CalculationLineType;
use App\Models\PriceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceItem>
 */
class PriceItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => 'Renovion praktijkprijzen',
            'edition' => '2026',
            'name' => fake()->unique()->words(3, true),
            'type' => fake()->randomElement(CalculationLineType::cases()),
            'unit' => fake()->randomElement(['m2', 'm1', 'uur', 'stuk', 'post']),
            'unit_price' => fake()->randomFloat(2, 5, 250),
            'surcharge_pct' => fake()->randomElement([0, 5, 10]),
            'index_factor' => 1,
            'active' => true,
            'last_checked_at' => today(),
        ];
    }
}
