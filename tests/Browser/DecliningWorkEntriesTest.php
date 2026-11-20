<?php

use App\Enums\WorkEntryState;
use App\Http\Controllers\WorkEntries\WorkEntryController;
use App\Http\Controllers\WorkEntries\WorkEntryDeclineController;
use App\Models\Employee;
use App\Models\WorkEntry;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(WorkEntryDeclineController::class, WorkEntryController::class);

beforeEach(function () {
    travelTo('2026-11-13 09:00');
});

it('lets a Manager decline ticked Work Entries with a reason, which then leave the list', function () {
    $ben = managerBen();
    $cara = recorder(['manager_id' => $ben->id]);
    $relaunch = assignmentOf($cara);
    $interviews = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10', 'description' => 'Interviews']);
    $hiring = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-11', 'description' => 'Hiring sync']);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-12', 'description' => 'Checkout API']);

    actingAs($ben);

    visit(route('approvals.index'))
        ->click("[aria-label=\"Select Cara's Work Entry on Nov 10, 2026\"]")
        ->click("[aria-label=\"Select Cara's Work Entry on Nov 11, 2026\"]")
        ->click('[data-slot="selection-bar"] >> internal:role=button[name="Decline…"s]')
        ->assertSee('Decline 2 Work Entries')
        ->click('internal:role=dialog >> internal:role=button[name="Decline 2 Work Entries"s]')
        ->assertPresent('#decline-reason:invalid')
        ->fill('#decline-reason', 'Interviews go on Internal, not Acme')
        ->click('internal:role=dialog >> internal:role=button[name="Decline 2 Work Entries"s]')
        ->assertSee('2 Work Entries declined.')
        ->assertDontSee('Interviews')
        ->assertSee('Checkout API')
        ->assertNotPresent('[data-slot="selection-bar"]')
        ->assertNoJavaScriptErrors();

    expect($interviews->refresh()->state)->toBe(WorkEntryState::Declined)
        ->and($hiring->refresh()->decline_reason)->toBe('Interviews go on Internal, not Acme');
});

it('shows the Employee a Declined Work Entry struck through with its reason, uncounted, and sends it back to Pending when changed', function () {
    $ben = Employee::factory()->create(['name' => 'Ben']);
    $cara = recorder();
    $relaunch = assignmentOf($cara);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10', 'minutes' => 300, 'description' => 'Checkout API']);
    $declined = WorkEntry::factory()->for($relaunch)->declined($ben, 'Interviews go on Internal, not Acme')
        ->create(['date' => '2026-11-10', 'minutes' => 90, 'description' => 'Interviews']);

    actingAs($cara);

    visit(route('work-entries.index'))
        ->assertSeeIn('[data-slot="stat-strip"]', 'Declined, not counted')
        ->assertSeeIn('[data-slot="stat-strip"]', '1.5 h')
        ->assertSeeIn('[data-test="decline-reason"]', 'Declined by Ben: Interviews go on Internal, not Acme')
        ->assertSee('Declined')
        ->click('internal:role=button[name="Calendar"s]')
        ->assertPresent('[data-date="2026-11-10"] [data-state="declined"]')
        ->assertSeeIn('[aria-label="Legend"]', 'Declined')
        ->click('[data-calendar-day="2026-11-10"]')
        ->assertSeeIn('[data-panel-date="2026-11-10"]', 'Declined by Ben: Interviews go on Internal, not Acme')
        ->click('[data-panel-date="2026-11-10"] >> internal:role=button[name="Edit"s]')
        ->assertSeeIn('[data-panel-date="2026-11-10"]', 'Saving sends it back to Pending, for approval again.')
        ->assertNoJavaScriptErrors();

    visit(route('work-entries.index'))
        ->click('[aria-label="More for Interviews on Tuesday 10 November"]')
        ->click('internal:role=menuitem[name="Edit"s]')
        ->assertSee('Saving sends it back to Pending, for approval again.')
        ->fill('#work-entry-description-'.$declined->id, 'Interviews, on Internal')
        ->click('Save')
        ->assertSee('Work Entry changed.')
        ->assertNotPresent('[data-test="decline-reason"]')
        ->assertDontSeeIn('[data-slot="stat-strip"]', 'Declined')
        ->assertNoJavaScriptErrors();

    expect($declined->refresh()->state)->toBe(WorkEntryState::Pending)
        ->and($declined->decline_reason)->toBeNull();
});
