<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected static ?string $password;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'department_id' => Department::factory(),
            'start_date' => today()->subDays(fake()->numberBetween(7, 5 * 365))->toDateString(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function invited(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
            'password' => Str::random(40),
        ]);
    }

    /** Well before the dates tests freeze time at, so Paid Days, Assignments and Work Entries don't hang on the random default Start Date. */
    public function startedLongAgo(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => '2025-01-06',
        ]);
    }

    public function reportingTo(Employee $manager): static
    {
        return $this->state(fn (array $attributes) => [
            'manager_id' => $manager->id,
        ]);
    }

    public function future(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => today()->addMonth()->toDateString(),
        ]);
    }

    public function former(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => today()->subYears(2)->toDateString(),
            'leaving_date' => today()->subMonth()->toDateString(),
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
