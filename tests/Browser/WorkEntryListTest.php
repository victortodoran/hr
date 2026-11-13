<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Enums\WorkEntryState;
use App\Http\Controllers\LockController;
use App\Http\Controllers\ReopeningController;
use App\Http\Controllers\WorkEntryController;
use App\Http\Controllers\WorkEntryListController;
use App\Models\Employee;
use App\Models\WorkEntry;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(WorkEntryListController::class, ReopeningController::class, LockController::class, WorkEntryController::class);

beforeEach(function () {
    travelTo('2026-11-13 09:00');
});

it('filters to Approved Work Entries, then reopens one and locks another', function () {
    $finn = employeeWith([Permission::ReopenWorkEntries->value => Scope::Everyone, Permission::LockWorkEntries->value => null], ['name' => 'Finn']);
    $cara = Employee::factory()->create(['name' => 'Cara']);
    $relaunch = assignmentOf($cara);
    $toReopen = WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2026-11-09', 'description' => 'Reviewing the API']);
    $toLock = WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2026-11-10', 'description' => 'Fixing the login']);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-11', 'description' => 'Writing specs']);

    actingAs($finn);

    visit(route('work-entry-list.index'))
        ->assertSee('Writing specs')
        ->click('[aria-label="State"]')
        ->click('internal:role=option[name="Approved"s]')
        ->assertQueryStringHas('state', 'approved')
        ->assertDontSee('Writing specs')
        ->click('[aria-label="Select Cara\'s Work Entry on Nov 9, 2026"]')
        ->click('Reopen')
        ->assertSee('1 Work Entry reopened.')
        ->assertDontSee('Reviewing the API')
        ->click('[aria-label="Select Cara\'s Work Entry on Nov 10, 2026"]')
        ->click('internal:role=button[name="Lock"s]')
        ->assertSee('1 Approved Work Entry will be locked')
        ->click('Lock for good')
        ->assertSee('1 Work Entry locked.')
        ->assertNoJavaScriptErrors();

    expect($toReopen->refresh()->state)->toBe(WorkEntryState::Pending)
        ->and($toLock->refresh()->state)->toBe(WorkEntryState::Locked);
});

it("opens an Employee's week from the list under their name, while the viewer's own week has no Employee picker", function () {
    $finn = employeeWith([Permission::ViewWorkEntries->value => Scope::Everyone], ['name' => 'Finn']);
    $cara = Employee::factory()->create(['name' => 'Cara']);
    WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-11', 'description' => 'Writing specs']);

    actingAs($finn);

    visit(route('work-entries.index'))
        ->assertSee('My Work Entries')
        ->assertNotPresent('[aria-label="Employee"]')
        ->assertNoJavaScriptErrors();

    visit(route('work-entry-list.index'))
        ->click('internal:role=link[name="Cara"s]')
        ->assertSee("Cara's Work Entries")
        ->assertSee('Writing specs')
        ->assertNotPresent('[aria-label="Employee"]')
        ->assertNoJavaScriptErrors();
});
