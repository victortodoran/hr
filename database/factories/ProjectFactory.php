<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'start_date' => today()->subMonths(fake()->numberBetween(1, 24))->toDateString(),
        ];
    }

    public function forClient(?Client $client = null): static
    {
        return $this->state(fn (array $attributes) => [
            'client_id' => $client->id ?? Client::factory(),
        ]);
    }

    public function internal(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_internal' => true,
            'start_date' => null,
            'end_date' => null,
            'client_id' => null,
        ]);
    }
}
