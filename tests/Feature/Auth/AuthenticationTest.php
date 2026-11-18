<?php

namespace Tests\Feature\Auth;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_employees_are_rate_limited(): void
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
