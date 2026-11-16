<?php

use App\Enums\PayKind;
use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\EmployeeController;
use App\Models\CompanySettings;
use App\Models\Employee;
use App\Models\PayEntry;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(EmployeeController::class);

beforeEach(function () {
    travelTo('2026-11-13 09:00');
});

it('opens every tab of an Employee\'s page, keeping the header, leading Time Off with the Paid Days left, and shows each Assignment\'s Pay on a phone', function () {
    CompanySettings::factory()->create();
    $ana = Employee::factory()->top()->create(['name' => 'Ana Popescu']);
    $cara = Employee::factory()->reportingTo($ana)->create(['name' => 'Cara Lane']);
    $relaunch = assignmentOf($cara, 'Relaunch', ['start_date' => '2026-01-05']);
    PayEntry::factory()->for($relaunch)->create(['effective_from' => '2026-01-05', 'kind' => PayKind::Hourly, 'amount' => 2500]);
    assignmentOf($cara, 'Data Audit', ['start_date' => '2026-01-05', 'end_date' => '2026-09-15']);
    $viewer = employeeWith([
        Permission::ViewEmployeeRecords->value => Scope::Everyone,
        Permission::ViewAssignments->value => Scope::Everyone,
        Permission::ViewPay->value => Scope::Everyone,
        Permission::ViewTimeOff->value => Scope::Everyone,
    ]);

    actingAs($viewer);

    visit(route('employees.show', $cara))
        ->assertSee('Current Employee')
        ->assertSee('Organisation')
        ->assertSeeLink('Ana Popescu')
        ->click('internal:role=tab[name="Assignments & Pay"s]')
        ->assertSee('Current Employee')
        ->assertSeeIn('[data-test="assignment"]', 'Relaunch')
        ->assertSee('Ended')
        ->click('internal:role=tab[name="Time Off"s]')
        ->assertSee('Current Employee')
        ->assertSeeIn('[data-test="paid-days-left"]', 'Paid Days left in 2026')
        ->assertNoJavaScriptErrors();

    visit(route('employees.show', ['employee' => $cara, 'tab' => 'assignments']))
        ->resize(390, 844)
        ->assertSee('€25.00 per hour')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
});
