<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Enums\WorkEntryState;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\WorkEntryDeclineController;
use App\Models\Employee;
use App\Models\WorkEntry;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(EmployeeController::class, ApprovalController::class, WorkEntryDeclineController::class);

beforeEach(function () {
    travelTo('2026-11-13 09:00');
});

it('lets a Manager filter their Report\'s Work Entries on the Employee page, then approve one and decline another, staying on the tab', function () {
    $ana = Employee::factory()->top()->create(['name' => 'Ana']);
    $ben = Employee::factory()->create(['name' => 'Ben', 'manager_id' => $ana->id]);
    giveRole($ben, [Permission::ViewWorkEntries->value => Scope::Reports]);
    $cara = Employee::factory()->create(['name' => 'Cara', 'manager_id' => $ben->id]);
    $relaunch = assignmentOf($cara);
    $toApprove = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-09', 'description' => 'Reviewing the API']);
    $toDecline = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10', 'description' => 'Interviews']);
    WorkEntry::factory()->for($relaunch)->approved($ben)->create(['date' => '2026-11-11', 'description' => 'Writing specs']);

    actingAs($ben);

    visit(route('employees.show', $cara))
        ->click('internal:role=tab[name="Work Entries"s]')
        ->assertSee('Writing specs')
        ->click('[aria-label="State"]')
        ->click('internal:role=option[name="Pending"s]')
        ->assertQueryStringHas('state', 'pending')
        ->assertQueryStringHas('tab', 'work-entries')
        ->assertDontSee('Writing specs')
        ->click('[aria-label="Select Cara\'s Work Entry on Nov 9, 2026"]')
        ->click('internal:role=button[name="Approve"s]')
        ->assertSee('1 Work Entry approved.')
        ->assertDontSee('Reviewing the API')
        ->click('[aria-label="Select Cara\'s Work Entry on Nov 10, 2026"]')
        ->click('Decline…')
        ->fill('#decline-reason', 'Interviews go on Internal')
        ->click('internal:role=dialog >> internal:role=button[name="Decline"s]')
        ->assertSee('declined')
        ->assertQueryStringHas('tab', 'work-entries')
        ->assertNoJavaScriptErrors();

    expect($toApprove->refresh()->state)->toBe(WorkEntryState::Approved)
        ->and($toDecline->refresh()->state)->toBe(WorkEntryState::Declined);
});

it('shows an Employee their own Work Entries on My Page with a way to record hours, fitting a phone', function () {
    $cara = Employee::factory()->create(['name' => 'Cara', 'manager_id' => Employee::factory()->top()->create()->id]);
    giveRole($cara, [Permission::ManageWorkEntries->value => Scope::Own]);
    WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-09', 'description' => 'Reviewing the API']);

    actingAs($cara);

    visit(route('employees.show', ['employee' => $cara, 'tab' => 'work-entries']))
        ->resize(390, 844)
        ->assertSee('Reviewing the API')
        ->assertSeeLink('Record hours')
        ->assertDontSee('selected')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
});
