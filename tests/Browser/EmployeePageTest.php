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
use App\Models\TravelRequest;
use App\Models\WorkEntry;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(EmployeeController::class, TravelRequestController::class);

beforeEach(function () {
    travelTo('2026-11-13 09:00');
});

it('opens every tab of an Employee\'s page, keeping the header and its facts, leading Time Off with the Paid Days left, and shows each Assignment\'s Pay on a phone', function () {
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
        ->assertSee('Reporting Chain')
        ->assertSeeLink('Ana Popescu')
        ->click('internal:role=tab[name="Assignments & Pay"s]')
        ->assertSeeLink('Ana Popescu')
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

it('gives the Pay button only to those who manage Pay, while those who only view it see the Pay in effect', function () {
    CompanySettings::factory()->create();
    $cara = Employee::factory()->startedLongAgo()->create(['name' => 'Cara Lane']);
    $relaunch = assignmentOf($cara, 'Relaunch', ['start_date' => '2026-01-05']);
    PayEntry::factory()->for($relaunch)->create(['effective_from' => '2026-01-05', 'kind' => PayKind::Hourly, 'amount' => 2500]);
    $viewsPay = employeeWith([
        Permission::ViewEmployeeRecords->value => Scope::Everyone,
        Permission::ViewPay->value => Scope::Everyone,
    ]);
    $managesPay = employeeWith([
        Permission::ViewEmployeeRecords->value => Scope::Everyone,
        Permission::ManagePay->value => Scope::Everyone,
    ]);

    actingAs($viewsPay);

    visit(route('employees.show', ['employee' => $cara, 'tab' => 'assignments']))
        ->assertSeeIn('[data-test="assignment"]', '€25.00 per hour')
        ->assertMissing('[aria-label="Pay on Relaunch"]')
        ->assertNoJavaScriptErrors();

    actingAs($managesPay);

    visit(route('employees.show', ['employee' => $cara, 'tab' => 'assignments']))
        ->click('[aria-label="Pay on Relaunch"]')
        ->assertSee('Add Pay')
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

it('shows an Employee\'s Work Entries, Travel Requests and Performance Evaluations in tabs, opening the full list on the same Period, and fits a phone', function () {
    CompanySettings::factory()->create();
    $ana = Employee::factory()->create(['name' => 'Ana Popescu']);
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ana)->create(['name' => 'Cara Lane']);
    $bea = Employee::factory()->startedLongAgo()->reportingTo($ana)->create(['name' => 'Bea Dunn']);
    WorkEntry::factory()->for(assignmentOf($cara, 'Relaunch'))->create(['date' => '2026-11-10', 'description' => 'Wrote the release notes']);
    WorkEntry::factory()->for(assignmentOf($bea, 'Relaunch'))->create(['date' => '2026-11-10', 'description' => 'Fixed the login page']);
    TravelRequest::factory()->on(assignmentOf($cara, 'Data Audit'))->create(['destination' => 'Lisbon', 'first_date' => '2026-11-20', 'last_date' => '2026-11-22']);
    PerformanceEvaluation::factory()->of($cara)->create(['year' => 2026]);
    $viewer = employeeWith([
        Permission::ViewEmployeeRecords->value => Scope::Everyone,
        Permission::ViewWorkEntries->value => Scope::Everyone,
        Permission::ViewPerformanceEvaluations->value => Scope::Everyone,
        Permission::ViewTravel->value => Scope::Everyone,
    ]);

    actingAs($viewer);

    visit(route('employees.show', $cara))
        ->click('internal:role=tab[name="Work"s]')
        ->assertSee('Wrote the release notes')
        ->assertDontSee('Fixed the login page')
        ->click('internal:role=tab[name="Travel"s]')
        ->assertSeeIn('[data-test="travel-request"]', 'Lisbon')
        ->click('internal:role=tab[name="Performance"s]')
        ->assertSeeIn('[data-test="performance-evaluation"]', '2026')
        ->assertNoJavaScriptErrors();

    visit(route('employees.show', ['employee' => $cara, 'tab' => 'work']))
        ->click('internal:role=link[name="Open in Work Entries"s]')
        ->assertPathIs(route('work-entry-list.index', absolute: false))
        ->assertQueryStringHas('employee', (string) $cara->id)
        ->assertQueryStringHas('month', '2026-11')
        ->assertSeeIn('[aria-label="Employee"]', 'Cara Lane (1)')
        ->assertNoJavaScriptErrors();

    visit(route('employees.show', ['employee' => $cara, 'tab' => 'work']))
        ->resize(390, 844)
        ->assertSeeIn('[data-test="work-entry"]', 'Tue 10')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
});

it('asks for a new Manager for the one Employee still reporting to a Former Employee, and fits a phone', function () {
    $ana = Employee::factory()->create(['name' => 'Ana Popescu']);
    $ben = Employee::factory()->former()->reportingTo($ana)->create(['name' => 'Ben Ionescu']);
    $lia = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Lia Rusu']);

    actingAs(employeeWith([Permission::ViewEmployeeRecords->value => Scope::Everyone, Permission::ManageEmployees->value => Scope::Everyone]));

    visit(route('employees.show', $ben))
        ->resize(390, 844)
        ->assertSee('Former Employee')
        ->assertSeeIn('[data-test="reports-needing-a-manager"]', 'Ben Ionescu has left, but Lia Rusu still reports to them')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->click('internal:role=link[name="Choose a new Manager for Lia Rusu"s]')
        ->assertPathIs(route('employees.edit', $lia, absolute: false))
        ->assertNoJavaScriptErrors();
});

it('edits an Employee\'s Paid Days per year in a dialog that warns it changes past years too', function () {
    $cara = Employee::factory()->startedLongAgo()->create(['name' => 'Cara Lane', 'paid_days' => 20]);

    actingAs(employeeWith([
        Permission::ViewEmployeeRecords->value => Scope::Everyone,
        Permission::ViewTimeOff->value => Scope::Everyone,
        Permission::ManagePaidDays->value => Scope::Everyone,
    ]));

    visit(route('employees.show', ['employee' => $cara, 'tab' => 'time-off']))
        ->assertSee('20 days')
        ->click('internal:role=button[name="Edit Paid Days per year"s]')
        ->assertSee('This also changes past years')
        ->fill('#paid-days', '25')
        ->click('internal:role=button[name="Save"s]')
        ->assertSee('25 days')
        ->assertNoJavaScriptErrors();

    expect($cara->refresh()->paid_days)->toBe(25);
});
