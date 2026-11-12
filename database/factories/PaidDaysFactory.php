<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\PaidDays;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaidDays>
 */
class PaidDaysFactory extends Factory
{
    /**
     * Paid Days for the current year.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'year' => today()->year,
            'days' => fake()->numberBetween(0, 30),
        ];
    }
}
