<?php

namespace Tests\Feature\Settings;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_employees_can_neither_change_their_own_name_or_email_nor_delete_their_account(): void
    {
        $employee = Employee::factory()->create(['name' => 'Cara Lane', 'email' => 'cara@acme.test']);

        $this->actingAs($employee)
            ->patch('/settings/profile', [
                'name' => 'Someone Else',
                'email' => 'someone@example.com',
            ])
            ->assertMethodNotAllowed();

        $this->actingAs($employee)
            ->delete('/settings/profile', [
                'password' => 'password',
            ])
            ->assertMethodNotAllowed();

        $employee->refresh();

        $this->assertSame('Cara Lane', $employee->name);
        $this->assertSame('cara@acme.test', $employee->email);
        $this->assertAuthenticatedAs($employee);
    }
}
