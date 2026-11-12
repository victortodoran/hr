<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\WorkEntryController;
use App\Http\Requests\WorkEntryRequest;
use App\Models\Employee;
use App\Models\WorkEntry;
use App\Policies\WorkEntryPolicy;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertModelExists;
use function Pest\Laravel\assertModelMissing;
use function Pest\Laravel\travelTo;

covers(WorkEntryController::class, WorkEntryRequest::class, WorkEntryPolicy::class);

beforeEach(function () {
    travelTo('2026-11-11 09:00');
});

it('lets an Employee correct one of their Work Entries, moving it to another Assignment and week', function () {
    $cara = recorder();
    $workEntry = WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-10', 'minutes' => 180, 'description' => 'API work']);
    $billing = assignmentOf($cara, 'Billing');

    $response = actingAs($cara)->patch(route('work-entries.update', $workEntry), workEntryForm($billing, ['date' => '2026-11-04', 'hours' => '1.5', 'description' => 'Invoices']));

    $response->assertRedirect(route('work-entries.index', ['employee' => $cara->id, 'week' => '2026-11-02']))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Work Entry changed.']);
    assertDatabaseHas('work_entries', ['id' => $workEntry->id, 'assignment_id' => $billing->id, 'date' => '2026-11-04', 'minutes' => 90, 'description' => 'Invoices']);
});

it('leaves the Work Entry being corrected out of the 24-hour day', function () {
    $cara = recorder();
    $relaunch = assignmentOf($cara);
    $workEntry = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10', 'minutes' => 16 * 60]);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10', 'minutes' => 4 * 60]);

    actingAs($cara)->patch(route('work-entries.update', $workEntry), workEntryForm($relaunch, ['date' => '2026-11-10', 'hours' => '20']))
        ->assertValid();

    expect($workEntry->refresh()->minutes)->toBe(20 * 60);
});

it('refuses moving a Work Entry to another Employee\'s Assignment', function () {
    $cara = recorder();
    $workEntry = WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-10']);
    $dansAssignment = assignmentOf(Employee::factory()->create());

    $response = actingAs($cara)->patch(route('work-entries.update', $workEntry), workEntryForm($dansAssignment));

    $response->assertInvalid(['assignment_id' => 'The selected Assignment is invalid.']);
    expect($workEntry->refresh()->assignment_id)->not->toBe($dansAssignment->id);
});

it('lets an Employee delete one of their Work Entries', function () {
    $cara = recorder();
    $workEntry = WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-10']);

    $response = actingAs($cara)->delete(route('work-entries.destroy', $workEntry));

    $response->assertRedirect(route('work-entries.index', ['employee' => $cara->id, 'week' => '2026-11-09']))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Work Entry deleted.']);
    assertModelMissing($workEntry);
});

it('forbids changing or deleting a Work Entry once it is Approved or Locked', function (string $state, string $method, string $route) {
    $cara = recorder();
    $relaunch = assignmentOf($cara);
    $workEntry = WorkEntry::factory()->for($relaunch)->{$state}()->create(['date' => '2026-11-10', 'minutes' => 60]);

    actingAs($cara)->{$method}(route($route, $workEntry), workEntryForm($relaunch, ['hours' => '5']))
        ->assertForbidden();

    assertModelExists($workEntry);
    assertDatabaseHas('work_entries', ['id' => $workEntry->id, 'minutes' => 60]);
})->with(['approved', 'locked'])->with([
    'change' => ['patch', 'work-entries.update'],
    'delete' => ['delete', 'work-entries.destroy'],
]);

it('forbids changing or deleting Work Entries of an Employee outside the manage work entries Scope', function (string $method, string $route) {
    $ben = employeeWith([Permission::ManageWorkEntries->value => Scope::Own, Permission::ViewWorkEntries->value => Scope::Everyone]);
    $cara = Employee::factory()->create();
    $relaunch = assignmentOf($cara);
    $workEntry = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10', 'minutes' => 60]);

    actingAs($ben)->{$method}(route($route, $workEntry), workEntryForm($relaunch, ['hours' => '5']))
        ->assertForbidden();

    assertModelExists($workEntry);
    assertDatabaseHas('work_entries', ['id' => $workEntry->id, 'minutes' => 60]);
})->with([
    'change' => ['patch', 'work-entries.update'],
    'delete' => ['delete', 'work-entries.destroy'],
]);
