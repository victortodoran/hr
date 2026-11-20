<?php

namespace Tests\Feature\Auth;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_employees_are_redirected_to_the_email_verification_prompt(): void
    {
        $employee = Employee::factory()->unverified()->create();

        $response = $this->actingAs($employee)->get(route('security.edit'));

        $response->assertRedirect(route('verification.notice'));
    }
}
