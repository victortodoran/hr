<?php

namespace Tests\Feature\Auth;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_password_screen_can_be_rendered(): void
    {
        $employee = Employee::factory()->create();

        $response = $this->actingAs($employee)->get(route('password.confirm'));

        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('auth/ConfirmPassword'),
        );
    }
}
