<?php

namespace Database\Factories;

use App\Models\Quote;
use App\Models\QuoteLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteLine>
 */
class QuoteLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->randomFloat(0, 5, 80);
        $unitPrice = fake()->randomFloat(2, 15, 95);

        return [
            'quote_id' => Quote::factory(),
            'description' => fake()->randomElement([
                'Stucwerk wanden', 'Stucwerk plafond', 'Schilderwerk binnen',
                'Tegelwerk vloer', 'Tegelwerk wand', 'Timmerwerk', 'Voorbereidende werkzaamheden',
            ]),
            'quantity' => $quantity,
            'unit' => fake()->randomElement(['m²', 'uur', 'stuks']),
            'unit_price' => $unitPrice,
            'vat_rate' => 21,
            'total' => round($quantity * $unitPrice, 2),
            'position' => 0,
        ];
    }
}
