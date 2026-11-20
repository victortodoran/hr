<?php

namespace Database\Factories;

use App\Enums\Outcome;
use App\Enums\PerformanceEvaluationState;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformanceEvaluation>
 */
class PerformanceEvaluationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'year' => today()->year,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => PerformanceEvaluationState::Completed,
            'outcome' => Outcome::Met,
            'summary' => fake()->sentence(),
            'completed_at' => now()->subHour(),
        ])->has(Goal::factory()->count(2)->state(['outcome' => Outcome::Met]));
    }

    public function of(Employee $employee): static
    {
        return $this->state(fn (array $attributes) => ['employee_id' => $employee->id]);
    }
}
