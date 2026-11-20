<?php

namespace Database\Factories;

use App\Enums\TimeOffState;
use App\Models\Employee;
use App\Models\TimeOff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeOff>
 */
class TimeOffFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $first = today()->addMonth()->startOfWeek();

        return [
            'employee_id' => Employee::factory(),
            'first_date' => $first->toDateString(),
            'last_date' => $first->addDays(4)->toDateString(),
            'is_paid' => true,
        ];
    }

    public function unpaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_paid' => false,
        ]);
    }

    public function approved(?Employee $approver = null): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => TimeOffState::Approved,
            'decided_by' => $approver ?? Employee::factory(),
            'decided_at' => now()->subHour(),
        ]);
    }

    public function declined(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => TimeOffState::Declined,
            'decided_by' => Employee::factory(),
            'decided_at' => now()->subHour(),
            'decline_reason' => fake()->sentence(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => TimeOffState::Cancelled,
            'cancelled_at' => now()->subHour(),
        ]);
    }
}
