<?php

namespace Tests\Feature\Auth;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get(route('login'));

        $response->assertOk();
    }

    public function test_employees_can_authenticate_using_the_login_screen()
    {
        $employee = Employee::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $employee->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_employees_with_two_factor_enabled_are_redirected_to_two_factor_challenge()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $employee = Employee::factory()->withTwoFactor()->create();

        $response = $this->post(route('login'), [
            'email' => $employee->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.login'));
        $response->assertSessionHas('login.id', $employee->id);
        $this->assertGuest();
    }

    public function test_employees_can_not_authenticate_with_invalid_password()
    {
        $employee = Employee::factory()->create();

        $this->post(route('login.store'), [
            'email' => $employee->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_employees_can_logout()
    {
        $employee = Employee::factory()->create();

        $response = $this->actingAs($employee)->post(route('logout'));

        $response->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_employees_are_rate_limited()
    {
        $employee = Employee::factory()->create();

        RateLimiter::increment(md5('login'.implode('|', [$employee->email, '127.0.0.1'])), amount: 5);

        $response = $this->post(route('login.store'), [
            'email' => $employee->email,
            'password' => 'wrong-password',
        ]);

        $response->assertTooManyRequests();
    }
}
