<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\WorkEntryController;
use App\Models\WorkEntry;
use App\Policies\WorkEntryPolicy;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertModelExists;
use function Pest\Laravel\assertModelMissing;
use function Pest\Laravel\travelTo;

covers(WorkEntryController::class, WorkEntryPolicy::class);

beforeEach(function () {
    travelTo('2026-11-11 09:00');
});

it('lets nobody record Work Entries for the top Employee, neither they nor someone managing everyone\'s', function () {
    $ana = recorder(['is_top' => true]);
    $relaunch = assignmentOf($ana);
    $hr = employeeWith([Permission::ManageWorkEntries->value => Scope::Everyone]);

    actingAs($ana)->post(route('employees.work-entries.store', $ana), workEntryForm($relaunch))->assertForbidden();
    actingAs($hr)->post(route('employees.work-entries.store', $ana), workEntryForm($relaunch))->assertForbidden();

    expect(WorkEntry::query()->exists())->toBeFalse();
});

it('keeps the top Employee\'s Pending Work Entries from being changed, but lets them be deleted', function () {
    $ana = recorder(['is_top' => true]);
    $relaunch = assignmentOf($ana);
    $changed = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10', 'minutes' => 60]);
    $deleted = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10']);

    actingAs($ana)->patch(route('work-entries.update', $changed), workEntryForm($relaunch, ['hours' => '2']))->assertForbidden();
    actingAs($ana)->delete(route('work-entries.destroy', $deleted))->assertRedirect();

    expect($changed->refresh()->minutes)->toBe(60);
    assertModelExists($changed);
    assertModelMissing($deleted);
});

it('shows the top Employee their week with no recording or changing, only deleting Pending Work Entries', function () {
    $ana = recorder(['is_top' => true]);
    WorkEntry::factory()->for(assignmentOf($ana))->create(['date' => '2026-11-10']);

    actingAs($ana)->get(route('work-entries.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canManage', false)
            ->where('days.1.entries.0.can_edit', false)
            ->where('days.1.entries.0.can_delete', true));
});
