<?php

namespace Database\Factories;

use App\Enums\ActionSource;
use App\Enums\TimelineEventType;
use App\Models\Customer;
use App\Models\TimelineEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimelineEvent>
 */
class TimelineEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'type' => TimelineEventType::Notitie,
            'source' => ActionSource::Handmatig,
            'title' => fake()->sentence(5),
            'happened_at' => now()->subDays(fake()->numberBetween(0, 30)),
        ];
    }
}
