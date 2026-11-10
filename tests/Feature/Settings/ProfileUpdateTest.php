<?php

namespace Tests\Feature\Settings;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $employee = Employee::factory()->create();

        $response = $this
            ->actingAs($employee)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_employees_cannot_change_their_own_name_or_email(): void
    {
        $employee = Employee::factory()->create(['name' => 'Cara Lane', 'email' => 'cara@acme.test']);

        $this->actingAs($employee)
            ->patch('/settings/profile', [
                'name' => 'Someone Else',
                'email' => 'someone@example.com',
            ])
            ->assertMethodNotAllowed();

        $employee->refresh();

        $this->assertSame('Cara Lane', $employee->name);
        $this->assertSame('cara@acme.test', $employee->email);
    }

    public function test_employee_can_delete_their_account(): void
    {
        $employee = Employee::factory()->create();

        $response = $this
            ->actingAs($employee)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull($employee->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $employee = Employee::factory()->create();

        $response = $this
            ->actingAs($employee)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($employee->fresh());
    }
}
