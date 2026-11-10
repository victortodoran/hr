<?php

namespace Tests\Feature;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_employees_can_visit_the_dashboard()
    {
        $employee = Employee::factory()->create();
        $this->actingAs($employee);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_pages_receive_the_signed_in_employee()
    {
        $employee = Employee::factory()->create(['name' => 'Cara Lopez']);

        $this->actingAs($employee)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.employee.name', 'Cara Lopez')
                ->missing('auth.user')
            );
    }
}
