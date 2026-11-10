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

    public function test_profile_information_can_be_updated(): void
    {
        $employee = Employee::factory()->create();

        $response = $this
            ->actingAs($employee)
            ->patch(route('profile.update'), [
                'name' => 'Test Employee',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $employee->refresh();

        $this->assertSame('Test Employee', $employee->name);
        $this->assertSame('test@example.com', $employee->email);
        $this->assertNull($employee->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $employee = Employee::factory()->create();

        $response = $this
            ->actingAs($employee)
            ->patch(route('profile.update'), [
                'name' => 'Test Employee',
                'email' => $employee->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($employee->refresh()->email_verified_at);
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
