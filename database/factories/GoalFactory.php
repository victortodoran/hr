<?php

namespace Database\Factories;

use App\Models\Goal;
use App\Models\PerformanceEvaluation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Goal>
 */
class GoalFactory extends Factory
{
    /**
     * A Goal on a new Performance Evaluation.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'performance_evaluation_id' => PerformanceEvaluation::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->sentence(),
        ];
    }
}
