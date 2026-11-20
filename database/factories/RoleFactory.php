<?php

namespace Database\Factories;

use App\Enums\Scope;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->jobTitle().' Role',
        ];
    }

    /**
     * A null Scope marks a company-wide Permission.
     *
     * @param  array<string, Scope|null>  $grants
     */
    public function withPermissions(array $grants): static
    {
        return $this->afterCreating(function (Role $role) use ($grants) {
            foreach ($grants as $permission => $scope) {
                $role->permissions()->create(['permission' => $permission, 'scope' => $scope]);
            }
        });
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Admin',
            'is_admin' => true,
        ]);
    }
}
