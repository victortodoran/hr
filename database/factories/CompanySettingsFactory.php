<?php

namespace Database\Factories;

use App\Models\CompanySettings;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanySettings>
 */
class CompanySettingsFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'currency' => 'EUR',
        ];
    }
}
