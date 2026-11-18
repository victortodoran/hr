<?php

namespace Tests\Feature\Auth;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::emailVerification());
    }

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $employee = Employee::factory()->unverified()->create();

        $response = $this->actingAs($employee)->get(route('verification.notice'));

        $response->assertOk();
    }

    public function test_unverified_employees_are_redirected_to_the_email_verification_prompt(): void
    {
        $employee = Employee::factory()->unverified()->create();

        $response = $this->actingAs($employee)->get(route('security.edit'));

        $response->assertRedirect(route('verification.notice'));
    }
}
