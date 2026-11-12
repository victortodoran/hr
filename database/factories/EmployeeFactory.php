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
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
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

    /**
     * An Employee who has been added but hasn't accepted their invitation:
     * no usable password and an unverified email.
     */
    public function invited(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
            'password' => Str::random(40),
        ]);
    }

    /**
     * An Employee whose Start Date is still ahead.
     */
    public function future(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => today()->addMonth()->toDateString(),
        ]);
    }

    /**
     * An Employee whose Leaving Date has passed.
     */
    public function former(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => today()->subYears(2)->toDateString(),
            'leaving_date' => today()->subMonth()->toDateString(),
        ]);
    }

    /**
     * The single Employee at the top of the company, with no Manager.
     */
    public function top(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_top' => true,
            'manager_id' => null,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
