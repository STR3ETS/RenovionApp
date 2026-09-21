<?php

namespace Database\Factories;

use App\Enums\ScheduleEntryType;
use App\Models\ScheduleEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleEntry>
 */
class ScheduleEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => ScheduleEntryType::Project,
            'date' => now()->addDays(fake()->numberBetween(0, 7))->toDateString(),
        ];
    }
}
