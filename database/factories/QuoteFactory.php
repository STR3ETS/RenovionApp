<?php

namespace Database\Factories;

use App\Enums\QuoteStatus;
use App\Models\Customer;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'OFF-'.now()->year.'-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'customer_id' => Customer::factory(),
            'status' => QuoteStatus::Concept,
            'valid_until' => now()->addDays(30)->toDateString(),
        ];
    }

    public function sent(): static
    {
        return $this->state([
            'status' => QuoteStatus::Verstuurd,
            'sent_at' => now()->subDays(fake()->numberBetween(1, 7)),
        ]);
    }

    public function viewed(int $times = 1): static
    {
        return $this->state([
            'status' => QuoteStatus::Bekeken,
            'sent_at' => now()->subDays(fake()->numberBetween(3, 10)),
            'viewed_at' => now()->subDays(fake()->numberBetween(1, 3)),
            'viewed_count' => $times,
        ]);
    }
}
