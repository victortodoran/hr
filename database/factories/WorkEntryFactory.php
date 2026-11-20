<?php

namespace Database\Factories;

use App\Enums\WorkEntryState;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\WorkEntry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<WorkEntry>
 */
class WorkEntryFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory(),
            'date' => fn (array $attributes): string => Carbon::parse(Assignment::query()->whereKey($attributes['assignment_id'])->value('start_date'))->toDateString(),
            'minutes' => fake()->numberBetween(1, 32) * 15,
            'description' => fake()->sentence(),
        ];
    }

    public function approved(?Employee $approver = null): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => WorkEntryState::Approved,
            'approved_by' => $approver ?? Employee::factory(),
            'approved_at' => now()->subHour(),
        ]);
    }

    public function declined(?Employee $decliner = null, string $reason = 'Not on this Project'): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => WorkEntryState::Declined,
            'declined_by' => $decliner ?? Employee::factory(),
            'declined_at' => now()->subHour(),
            'decline_reason' => $reason,
        ]);
    }
}
