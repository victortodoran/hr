<?php

namespace Tests\Feature\Settings;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_page_is_displayed()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);
        Features::passkeys([
            'confirmPassword' => true,
        ]);

        $employee = Employee::factory()->create();

        $this->actingAs($employee)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Security')
                ->where('canManagePasskeys', true)
                ->where('passkeys', [])
                ->where('canManageTwoFactor', true)
                ->where('twoFactorEnabled', false),
            );
    }

    public function test_security_page_lists_only_the_employees_own_passkeys()
    {
        $this->skipUnlessFortifyHas(Features::passkeys());

        $employee = Employee::factory()->create();
        $colleague = Employee::factory()->create();
        $employee->passkeys()->create(['name' => 'Work laptop', 'credential_id' => 'cred-1', 'credential' => []]);
        $colleague->passkeys()->create(['name' => 'Colleague phone', 'credential_id' => 'cred-2', 'credential' => []]);

        $this->actingAs($employee)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('passkeys', 1)
                ->where('passkeys.0.name', 'Work laptop'),
            );
    }

    public function test_deleting_an_employee_deletes_their_passkeys()
    {
        $employee = Employee::factory()->create();
        $employee->passkeys()->create(['name' => 'Work laptop', 'credential_id' => 'cred-1', 'credential' => []]);

        $employee->delete();

        $this->assertDatabaseMissing('passkeys', ['credential_id' => 'cred-1']);
    }

    public function test_security_page_requires_password_confirmation_when_enabled()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        $employee = Employee::factory()->create();

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $response = $this->actingAs($employee)
            ->get(route('security.edit'));

        $response->assertRedirect(route('password.confirm'));
    }

    public function test_security_page_renders_without_two_factor_when_feature_is_disabled()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        config(['fortify.features' => []]);

        $employee = Employee::factory()->create();

        $this->actingAs($employee)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Security')
                ->where('canManagePasskeys', false)
                ->where('passkeys', [])
                ->where('canManageTwoFactor', false)
                ->missing('twoFactorEnabled')
                ->missing('requiresConfirmation'),
            );
    }

    public function test_password_can_be_updated()
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

    public function test_correct_password_must_be_provided_to_update_password()
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
