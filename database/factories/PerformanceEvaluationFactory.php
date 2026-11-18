<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\PerformanceEvaluation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformanceEvaluation>
 */
class PerformanceEvaluationFactory extends Factory
{
    /**
     * An Open Performance Evaluation for this year, with no Goals yet.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'year' => today()->year,
        ];
    }

    /**
     * For the Employee.
     */
    public function of(Employee $employee): static
    {
        return $this->state(fn (array $attributes) => ['employee_id' => $employee->id]);
    }
}
