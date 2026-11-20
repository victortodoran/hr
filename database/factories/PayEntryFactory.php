<?php

namespace Database\Factories;

use App\Enums\PayKind;
use App\Models\Assignment;
use App\Models\PayEntry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<PayEntry>
 */
class PayEntryFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory(),
            'effective_from' => fn (array $attributes): string => Carbon::parse(Assignment::query()->whereKey($attributes['assignment_id'])->value('start_date'))->toDateString(),
            'kind' => PayKind::Fixed,
            'amount' => fake()->numberBetween(2000, 9000) * 100,
        ];
    }

    public function hourly(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => PayKind::Hourly,
            'amount' => fake()->numberBetween(15, 90) * 100,
        ]);
    }
}
