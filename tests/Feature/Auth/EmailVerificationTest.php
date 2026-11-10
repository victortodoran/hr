<?php

namespace Tests\Feature\Auth;

use App\Models\Employee;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
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

        $response = $this->actingAs($employee)->get(route('appearance.edit'));

        $response->assertRedirect(route('verification.notice'));
    }

    public function test_email_can_be_verified(): void
    {
        $employee = Employee::factory()->unverified()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $employee->id, 'hash' => sha1($employee->email)],
        );

        $response = $this->actingAs($employee)->get($verificationUrl);

        Event::assertDispatched(Verified::class);

        $this->assertTrue($employee->refresh()->hasVerifiedEmail());
        $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $employee = Employee::factory()->unverified()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $employee->id, 'hash' => sha1('wrong-email')],
        );

        $this->actingAs($employee)->get($verificationUrl);

        Event::assertNotDispatched(Verified::class);
        $this->assertFalse($employee->refresh()->hasVerifiedEmail());
    }

    public function test_email_is_not_verified_with_invalid_employee_id(): void
    {
        $employee = Employee::factory()->unverified()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => 123, 'hash' => sha1($employee->email)],
        );

        $this->actingAs($employee)->get($verificationUrl);

        Event::assertNotDispatched(Verified::class);
        $this->assertFalse($employee->refresh()->hasVerifiedEmail());
    }

    public function test_verified_employee_is_redirected_to_dashboard_from_verification_prompt(): void
    {
        $employee = Employee::factory()->create();

        Event::fake();

        $response = $this->actingAs($employee)->get(route('verification.notice'));

        Event::assertNotDispatched(Verified::class);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_already_verified_employee_visiting_verification_link_is_redirected_without_firing_event_again(): void
    {
        $employee = Employee::factory()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $employee->id, 'hash' => sha1($employee->email)],
        );

        $this->actingAs($employee)->get($verificationUrl)
            ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

        Event::assertNotDispatched(Verified::class);
        $this->assertTrue($employee->refresh()->hasVerifiedEmail());
    }
}
