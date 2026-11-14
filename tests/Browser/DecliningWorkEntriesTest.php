<?php

use App\Enums\WorkEntryState;
use App\Http\Controllers\WorkEntryController;
use App\Http\Controllers\WorkEntryDeclineController;
use App\Models\Employee;
use App\Models\WorkEntry;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(WorkEntryDeclineController::class, WorkEntryController::class);

beforeEach(function () {
    travelTo('2026-11-13 09:00');
});

it('lets a Manager decline ticked Work Entries with a reason, which then leave the list', function () {
    $ben = Employee::factory()->create(['name' => 'Ben', 'manager_id' => Employee::factory()->top()->create()->id]);
    $cara = recorder(['manager_id' => $ben->id]);
    $relaunch = assignmentOf($cara);
    $interviews = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10', 'description' => 'Interviews']);
    $hiring = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-11', 'description' => 'Hiring sync']);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-12', 'description' => 'Checkout API']);

    actingAs($ben);

    visit(route('approvals.index'))
        ->click("[aria-label=\"Select Cara's Work Entry on Nov 10, 2026\"]")
        ->click("[aria-label=\"Select Cara's Work Entry on Nov 11, 2026\"]")
        ->click('Decline…')
        ->assertSee('Decline 2 Work Entries?')
        ->click('internal:role=dialog >> internal:role=button[name="Decline"s]')
        ->assertPresent('#decline-reason:invalid')
        ->fill('#decline-reason', 'Interviews go on Internal, not Acme')
        ->click('internal:role=dialog >> internal:role=button[name="Decline"s]')
        ->assertSee('2 Work Entries declined.')
        ->assertDontSee('Interviews')
        ->assertSee('Checkout API')
        ->assertSee('0 selected')
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
        ->assertSeeIn('[data-test="declined-hours"]', '1.5 h declined, not counted')
        ->assertSeeIn('[data-test="decline-reason"]', 'Declined by Ben: Interviews go on Internal, not Acme')
        ->assertSee('Declined')
        ->click('internal:role=tab[name="Calendar"i]')
        ->assertPresent('[data-date="2026-11-10"] [data-state="declined"]')
        ->assertSeeIn('[aria-label="Legend"]', 'Declined')
        ->click('[aria-label="Add or view entries, Tuesday 10 November"]')
        ->assertSeeIn('[data-panel-date="2026-11-10"]', 'Declined by Ben: Interviews go on Internal, not Acme')
        ->assertNoJavaScriptErrors();

    visit(route('work-entries.index'))
        ->click('[data-test="work-entry"]:has-text("Interviews") [aria-label="Edit Work Entry"]')
        ->fill('#work-entry-description-'.$declined->id, 'Interviews, on Internal')
        ->click('Save')
        ->assertSee('Work Entry changed.')
        ->assertNotPresent('[data-test="decline-reason"]')
        ->assertNotPresent('[data-test="declined-hours"]')
        ->assertNoJavaScriptErrors();

    expect($declined->refresh()->state)->toBe(WorkEntryState::Pending)
        ->and($declined->decline_reason)->toBeNull();
});
