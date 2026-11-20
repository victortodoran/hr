<?php

namespace Database\Factories;

use App\Enums\TravelRequestState;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\TravelRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelRequest>
 */
class TravelRequestFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $first = today()->addMonth();

        return [
            'employee_id' => Employee::factory(),
            'assignment_id' => fn (array $attributes): int => Assignment::factory()->create(['employee_id' => $attributes['employee_id']])->id,
            'destination' => fake()->city(),
            'first_date' => $first->toDateString(),
            'last_date' => $first->addDays(2)->toDateString(),
            'purpose' => fake()->sentence(),
        ];
    }

    public function on(Assignment $assignment): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_id' => $assignment->employee_id,
            'assignment_id' => $assignment->id,
        ]);
    }

    public function approved(?Employee $approver = null): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => TravelRequestState::Approved,
            'decided_by' => $approver ?? Employee::factory(),
            'decided_at' => now()->subHour(),
        ]);
    }

    public function declined(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => TravelRequestState::Declined,
            'decided_by' => Employee::factory(),
            'decided_at' => now()->subHour(),
            'decline_reason' => fake()->sentence(),
        ]);
    }

    public function cancelledAfterApproval(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => TravelRequestState::Cancelled,
            'decided_by' => Employee::factory(),
            'decided_at' => now()->subHours(2),
            'cancelled_at' => now()->subHour(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => TravelRequestState::Cancelled,
            'cancelled_at' => now()->subHour(),
        ]);
    }
}
