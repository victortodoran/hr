<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\WorkEntry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<WorkEntry>
 */
class WorkEntryFactory extends Factory
{
    /**
     * A few hours on the day the Assignment starts.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory(),
            'date' => fn (array $attributes): string => Carbon::parse(Assignment::query()->whereKey($attributes['assignment_id'])->value('start_date'))->toDateString(),
            'minutes' => fake()->numberBetween(1, 32) * 15,
            'description' => fake()->sentence(),
        ];
    }
}
