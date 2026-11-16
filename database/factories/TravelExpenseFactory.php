<?php

namespace Database\Factories;

use App\Enums\TravelExpenseCategory;
use App\Enums\TravelExpenseState;
use App\Models\Employee;
use App\Models\TravelExpense;
use App\Models\TravelRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelExpense>
 */
class TravelExpenseFactory extends Factory
{
    /**
     * A Pending Travel Expense paid yesterday on a new Approved Travel
     * Request. Its receipt path points to no file; pass one stored on the
     * receipt disk when a test reads it.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'travel_request_id' => TravelRequest::factory()->approved(),
            'paid_on' => today()->subDay()->toDateString(),
            'amount' => fake()->numberBetween(1000, 50000),
            'category' => fake()->randomElement(TravelExpenseCategory::cases()),
            'description' => fake()->sentence(),
            'receipt_path' => TravelExpense::RECEIPT_FOLDER.'/'.fake()->uuid().'.pdf',
        ];
    }

    /**
     * Approved by someone else an hour ago.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => TravelExpenseState::Approved,
            'approved_by' => Employee::factory(),
            'approved_at' => now()->subHour(),
        ]);
    }

    /**
     * Declined by someone else an hour ago, with a reason.
     */
    public function declined(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => TravelExpenseState::Declined,
            'declined_by' => Employee::factory(),
            'declined_at' => now()->subHour(),
            'decline_reason' => fake()->sentence(),
        ]);
    }

    /**
     * Approved and then locked by someone else an hour ago.
     */
    public function locked(): static
    {
        return $this->approved()->state(fn (array $attributes) => [
            'state' => TravelExpenseState::Locked,
            'locked_by' => Employee::factory(),
            'locked_at' => now()->subHour(),
        ]);
    }
}
