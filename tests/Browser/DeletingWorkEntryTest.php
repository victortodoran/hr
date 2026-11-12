<?php

use App\Http\Controllers\WorkEntryController;
use App\Models\WorkEntry;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(WorkEntryController::class);

beforeEach(function () {
    travelTo('2026-11-11 09:00');
});

it('asks before deleting a Work Entry, and Cancel keeps it', function () {
    $cara = recorder();
    $entry = WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-10', 'description' => 'Reviewing the API']);

    actingAs($cara);

    visit(route('work-entries.index'))
        ->click('Edit')
        ->click('Delete')
        ->assertSee('Delete this Work Entry?')
        ->click('internal:role=dialog[name="Delete this Work Entry?"s] >> internal:role=button[name="Cancel"s]')
        ->assertDontSee('Delete this Work Entry?')
        ->assertNoJavaScriptErrors();

    expect($entry->fresh())->not->toBeNull();
});
