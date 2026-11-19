<?php

use App\Enums\PayKind;
use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\Employees\EmployeeController;
use App\Http\Controllers\Travel\TravelRequestController;
use App\Models\Assignment;
use App\Models\CompanySettings;
use App\Models\Employee;
use App\Models\PayEntry;
use App\Models\PerformanceEvaluation;
use App\Models\WorkEntry;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(EmployeeController::class, TravelRequestController::class);

beforeEach(function () {
    travelTo('2026-11-13 09:00');
});

it('opens every tab of an Employee\'s page, keeping the header, leading Time Off with the Paid Days left, and shows each Assignment\'s Pay on a phone', function () {
    CompanySettings::factory()->create();
    $ana = Employee::factory()->create(['name' => 'Ana Popescu']);
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

it('drops an Assignment edit that was cancelled, so reopening shows what is saved and saving another change leaves it out', function () {
    $cara = Employee::factory()->startedLongAgo()->create(['name' => 'Cara Lane']);
    $relaunch = assignmentOf($cara, 'Relaunch', ['start_date' => '2026-01-05', 'capacity' => 100]);
    $manager = employeeWith([
        Permission::ViewEmployeeRecords->value => Scope::Everyone,
        Permission::ManageAssignments->value => Scope::Everyone,
    ]);

    actingAs($manager);

    visit(route('employees.show', ['employee' => $cara, 'tab' => 'assignments']))
        ->click('[aria-label="Edit the Assignment to Relaunch"]')
        ->fill("#assignment-capacity-{$relaunch->id}", '50')
        ->click('Cancel')
        ->click('[aria-label="Edit the Assignment to Relaunch"]')
        ->assertValue("#assignment-capacity-{$relaunch->id}", '100')
        ->fill("#assignment-job-title-{$relaunch->id}", 'Lead Developer')
        ->click('Save')
        ->assertSee('Lead Developer')
        ->assertNoJavaScriptErrors();

    $relaunch->refresh();
    expect($relaunch->capacity)->toBe(100)
        ->and($relaunch->job_title)->toBe('Lead Developer');
});

it('deletes an unused Assignment after asking, and offers no Delete on one with Work Entries', function () {
    $cara = Employee::factory()->startedLongAgo()->create(['name' => 'Cara Lane']);
    $mistake = assignmentOf($cara, 'Relaunch');
    $worked = assignmentOf($cara, 'Data Audit');
    WorkEntry::factory()->for($worked)->create();

    actingAs(employeeWith([
        Permission::ViewEmployeeRecords->value => Scope::Everyone,
        Permission::ManageAssignments->value => Scope::Everyone,
    ]));

    visit(route('employees.show', ['employee' => $cara, 'tab' => 'assignments']))
        ->assertMissing('[aria-label="Delete the Assignment to Data Audit"]')
        ->click('[aria-label="Delete the Assignment to Relaunch"]')
        ->assertSee('Delete the Assignment to Relaunch?')
        ->click('internal:role=button[name="Delete"s]')
        ->assertSee('Assignment deleted.')
        ->assertDontSeeIn('[data-test="assignment"]', 'Relaunch')
        ->assertNoJavaScriptErrors();

    expect(Assignment::query()->whereKey($mistake->id)->exists())->toBeFalse();
});

it('removes an Employee\'s Manager by choosing No Manager', function () {
    $ana = Employee::factory()->create(['name' => 'Ana Founder']);
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ana)->create(['name' => 'Cara Lane']);

    actingAs(employeeWhoManagesEmployees());

    visit(route('employees.edit', $cara))
        ->click('#manager_id')
        ->click('[role="option"] >> text=No Manager')
        ->click('Save')
        ->assertSee('Employee updated.')
        ->assertNoJavaScriptErrors();

    expect($cara->refresh()->manager_id)->toBeNull();
});

it('links an Employee\'s Overview to their Work Entries and Performance Evaluations, each list opening narrowed to them', function () {
    $ana = Employee::factory()->create(['name' => 'Ana Popescu']);
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ana)->create(['name' => 'Cara Lane']);
    $bea = Employee::factory()->startedLongAgo()->reportingTo($ana)->create(['name' => 'Bea Dunn']);
    WorkEntry::factory()->for(assignmentOf($bea, 'Relaunch'))->create(['date' => '2026-11-10']);
    PerformanceEvaluation::factory()->of($bea)->create();
    $viewer = employeeWith([
        Permission::ViewEmployeeRecords->value => Scope::Everyone,
        Permission::ViewWorkEntries->value => Scope::Everyone,
        Permission::ViewPerformanceEvaluations->value => Scope::Everyone,
    ]);

    actingAs($viewer);

    visit(route('employees.show', $cara))
        ->assertSee('See also')
        ->assertDontSee('Travel →')
        ->click('internal:role=link[name="Work →"s]')
        ->assertPathIs(route('work-entry-list.index', absolute: false))
        ->assertQueryStringHas('employee', (string) $cara->id)
        ->assertSeeIn('[aria-label="Employee"]', 'Cara Lane (0)')
        ->assertNoJavaScriptErrors();

    visit(route('employees.show', $cara))
        ->click('internal:role=link[name="Performance →"s]')
        ->assertPathIs(route('performance-evaluations.index', absolute: false))
        ->assertSeeIn('[aria-label="Employee"]', 'Cara Lane')
        ->assertSee('Cara Lane')
        ->assertDontSee('Bea Dunn')
        ->assertNoJavaScriptErrors();
});
