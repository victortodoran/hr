<?php

use App\Enums\TimeOffState;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\TimeOffApprovalController;
use App\Models\Employee;
use App\Models\TimeOff;
use App\Models\WorkEntry;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(ApprovalController::class, TimeOffApprovalController::class);

beforeEach(function () {
    travelTo('2026-12-01 09:00');
});

it('lets a Manager switch tabs, kept in the URL, and approve Time Off from its tab', function () {
    $ben = Employee::factory()->create(['name' => 'Ben', 'manager_id' => Employee::factory()->top()->create()->id]);
    $cara = recorder(['manager_id' => $ben->id]);
    WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-30', 'description' => 'Reviewing the API']);
    $timeOff = TimeOff::factory()->for($cara)->unpaid()->create(['first_date' => '2026-12-14', 'last_date' => '2026-12-16']);

    actingAs($ben);

    $page = visit(route('approvals.index'))
        ->assertSee('Reviewing the API')
        ->click('internal:role=tab[name="Time Off"i]')
        ->assertQueryStringHas('tab', 'time-off')
        ->assertSee('Cara')
        ->click('Approve')
        ->assertSee('Time Off approved.')
        ->assertQueryStringHas('tab', 'time-off')
        ->click('internal:role=tab[name="Work Entries"i]')
        ->assertQueryStringHas('tab', 'work-entries')
        ->assertSee('Reviewing the API')
        ->assertNoJavaScriptErrors();

    expect($timeOff->refresh()->state)->toBe(TimeOffState::Approved);
});
