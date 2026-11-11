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
            'capacity' => fake()->randomElement([20, 50, 80, 100]),
        ];
    }

    /**
     * The Employee's Internal Assignment: to the Internal Project, over their
     * whole period of work, with no internal Job Title yet and 0% capacity.
     */
    public function internal(): static
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => fn (): int => Project::query()->where('is_internal', true)->value('id') ?? Project::factory()->internal()->create()->id,
            'job_title' => null,
            'start_date' => fn (array $attributes): string => Carbon::parse(Employee::query()->whereKey($attributes['employee_id'])->value('start_date'))->toDateString(),
            'end_date' => fn (array $attributes): ?string => Employee::query()->whereKey($attributes['employee_id'])->value('leaving_date'),
            'capacity' => 0,
        ]);
    }
}
