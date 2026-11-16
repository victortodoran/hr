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
    /**
     * A Pending Travel Request for three days a month from now, on a new
     * open-ended Assignment of the Employee's.
     *
     * @return array<string, mixed>
     */
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

    /**
     * On one of the Employee's Assignments, for that Employee.
     */
    public function on(Assignment $assignment): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_id' => $assignment->employee_id,
            'assignment_id' => $assignment->id,
        ]);
    }

    /**
     * Approved by someone else an hour ago, unless told who.
     */
    public function approved(?Employee $approver = null): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => TravelRequestState::Approved,
            'decided_by' => $approver ?? Employee::factory(),
            'decided_at' => now()->subHour(),
        ]);
    }

    /**
     * Declined by someone else an hour ago, with a reason.
     */
    public function declined(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => TravelRequestState::Declined,
            'decided_by' => Employee::factory(),
            'decided_at' => now()->subHour(),
            'decline_reason' => fake()->sentence(),
        ]);
    }

    /**
     * Approved by someone else two hours ago, then cancelled by the
     * Employee an hour ago.
     */
    public function cancelledAfterApproval(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => TravelRequestState::Cancelled,
            'decided_by' => Employee::factory(),
            'decided_at' => now()->subHours(2),
            'cancelled_at' => now()->subHour(),
        ]);
    }

    /**
     * Cancelled by the Employee an hour ago, while it was Pending.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => TravelRequestState::Cancelled,
            'cancelled_at' => now()->subHour(),
        ]);
    }
}
