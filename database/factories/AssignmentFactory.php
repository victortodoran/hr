<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\Employee;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
{
    /**
     * Define the model's default state: an open-ended Assignment that starts
     * once both the Employee and the Project have.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'project_id' => Project::factory(),
            'job_title' => fake()->jobTitle(),
            'start_date' => fn (array $attributes): string => Carbon::parse(max(
                Employee::query()->whereKey($attributes['employee_id'])->value('start_date'),
                Project::query()->whereKey($attributes['project_id'])->value('start_date'),
            ))->toDateString(),
            'share' => fake()->randomElement([20, 50, 80, 100]),
        ];
    }
}
