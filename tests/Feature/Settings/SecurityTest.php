<?php

namespace Tests\Feature\Settings;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_page_is_displayed(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($employee)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Security')
                ->has('passwordRules'),
            );
    }

    public function test_security_page_requires_password_confirmation(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($employee)
            ->get(route('security.edit'))
            ->assertRedirect(route('password.confirm'));
    }

    public function test_password_can_be_updated(): void
    {
        $employee = Employee::factory()->create();

        $response = $this
            ->actingAs($employee)
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('security.edit'));

        $this->assertTrue(Hash::check('new-password', $employee->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $employee = Employee::factory()->create();

        $response = $this
            ->actingAs($employee)
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect(route('security.edit'));
    }
}
