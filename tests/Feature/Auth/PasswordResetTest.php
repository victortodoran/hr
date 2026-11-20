<?php

namespace Tests\Feature\Auth;

use App\Models\Employee;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $employee = Employee::factory()->create();

        $this->post(route('password.email'), ['email' => $employee->email]);

        Notification::assertSentTo($employee, ResetPassword::class);
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $employee = Employee::factory()->create();

        $this->post(route('password.email'), ['email' => $employee->email]);

        Notification::assertSentTo($employee, ResetPassword::class, function ($notification) use ($employee) {
            $response = $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $employee->email,
                'password' => 'a-new-password',
                'password_confirmation' => 'a-new-password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });

        $this->assertCredentials(['email' => $employee->email, 'password' => 'a-new-password']);
        $this->assertInvalidCredentials(['email' => $employee->email, 'password' => 'password']);
    }
}
