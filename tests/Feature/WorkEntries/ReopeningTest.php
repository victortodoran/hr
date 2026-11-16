<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Enums\WorkEntryState;
use App\Http\Controllers\WorkEntries\ApprovalController;
use App\Http\Controllers\WorkEntries\ReopeningController;
use App\Http\Controllers\WorkEntries\WorkEntryListController;
use App\Models\Employee;
use App\Models\WorkEntry;
use App\Policies\WorkEntryPolicy;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(ReopeningController::class, WorkEntryListController::class, ApprovalController::class, WorkEntryPolicy::class, Employee::class);

beforeEach(function () {
    travelTo('2026-11-11 09:00');
});

/**
 * Rae, who may reopen the Work Entries of her Reports (or the given grants
 * instead), below Ada at the top.
 *
 * @param  array<string, Scope|null>|null  $grants
 */
function reopener(?array $grants = null): Employee
{
    $ada = Employee::factory()->create(['name' => 'Ada']);

    return employeeWith($grants ?? [Permission::ReopenWorkEntries->value => Scope::Reports], ['name' => 'Rae', 'manager_id' => $ada->id]);
}

it('lists everyone a reopen Scope covers, offering to reopen their Approved Work Entries but never one\'s own', function () {
    $rae = reopener([Permission::ReopenWorkEntries->value => Scope::Everyone]);
    $ada = Employee::query()->findOrFail($rae->manager_id);
    $cara = Employee::factory()->reportingTo($ada)->create(['name' => 'Cara']);
    $relaunch = assignmentOf($cara);
    $ben = Employee::factory()->create(['name' => 'Ben']);
    $approved = WorkEntry::factory()->for($relaunch)->approved($ben)->create(['date' => '2026-11-10', 'minutes' => 60, 'description' => 'API', 'approved_at' => '2026-11-10 17:00']);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10']);
    $own = WorkEntry::factory()->for(assignmentOf($rae, 'Archive'))->approved($ben)->create(['date' => '2026-11-10']);

    $response = actingAs($rae)->get(route('work-entry-list.index', ['employee' => 'all', 'state' => 'approved']));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('work-entries/List')
        ->where('filters', ['period' => ['mode' => 'month', 'from' => '2026-11-01', 'to' => '2026-11-30', 'query' => ['month' => '2026-11']], 'employee' => 'all', 'project' => null, 'state' => 'approved'])
        ->where('canReopen', true)
        ->where('canLock', false)
        ->where('entries.data', [
            ['id' => $approved->id, 'date' => '2026-11-10', 'employee' => ['id' => $cara->id, 'name' => 'Cara'], 'project' => 'Relaunch', 'hours' => 1, 'description' => 'API', 'state' => 'approved', 'approved' => ['by' => 'Ben', 'on' => '2026-11-10'], 'locked' => null, 'declined' => null, 'can_reopen' => true, 'can_lock' => false],
            ['id' => $own->id, 'date' => '2026-11-10', 'employee' => ['id' => $rae->id, 'name' => 'Rae'], 'project' => 'Archive', 'hours' => $own->hours, 'description' => $own->description, 'state' => 'approved', 'approved' => ['by' => 'Ben', 'on' => '2026-11-11'], 'locked' => null, 'declined' => null, 'can_reopen' => false, 'can_lock' => false],
        ])
        ->where('employees', [['id' => $cara->id, 'name' => 'Cara', 'count' => 1], ['id' => $rae->id, 'name' => 'Rae', 'count' => 1]]));
});

it('reopens the selected Approved Work Entries, withdrawing their approval', function () {
    $rae = reopener();
    $relaunch = assignmentOf(Employee::factory()->reportingTo($rae)->create());
    $first = WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2026-11-09']);
    $second = WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2026-11-10']);
    $untouched = WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2026-11-10']);

    $response = actingAs($rae)->from(route('work-entry-list.index', ['state' => 'approved']))
        ->post(route('reopenings.store'), ['ids' => [$first->id, $second->id]]);

    $response->assertRedirect(route('work-entry-list.index', ['state' => 'approved']))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => '2 Work Entries reopened.']);
    foreach ([$first, $second] as $workEntry) {
        $workEntry->refresh();
        expect($workEntry->state)->toBe(WorkEntryState::Pending)
            ->and($workEntry->approved_by)->toBeNull()
            ->and($workEntry->approved_at)->toBeNull();
    }
    expect($untouched->refresh()->state)->toBe(WorkEntryState::Approved);
});

it('reopens only the selected Work Entries it may, and says how many it skipped', function () {
    $rae = reopener([Permission::ReopenWorkEntries->value => Scope::Everyone]);
    $relaunch = assignmentOf(Employee::factory()->reportingTo($rae)->create());
    $approved = WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2026-11-10']);
    $own = WorkEntry::factory()->for(assignmentOf($rae))->approved()->create(['date' => '2026-11-10']);
    $pending = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10']);
    $locked = WorkEntry::factory()->for($relaunch)->locked()->create(['date' => '2026-11-10']);

    $response = actingAs($rae)->post(route('reopenings.store'), ['ids' => [$approved->id, $approved->id, $own->id, $pending->id, $locked->id]]);

    $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => '1 Work Entry reopened, 3 skipped.']);
    expect($approved->refresh()->state)->toBe(WorkEntryState::Pending)
        ->and($own->refresh()->state)->toBe(WorkEntryState::Approved)
        ->and($pending->refresh()->state)->toBe(WorkEntryState::Pending)
        ->and($locked->refresh()->state)->toBe(WorkEntryState::Locked);
});

it('neither offers nor lets a Manager with no reopen Role reopen their Direct Report\'s Work Entries', function () {
    $ada = Employee::factory()->create();
    $ben = employeeWith([Permission::ViewWorkEntries->value => Scope::Reports], ['manager_id' => $ada->id]);
    $approved = WorkEntry::factory()->for(assignmentOf(Employee::factory()->reportingTo($ben)->create()))->approved()->create(['date' => '2026-11-10']);

    actingAs($ben)->get(route('work-entry-list.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canReopen', false)
            ->where('entries.data.0.id', $approved->id)
            ->where('entries.data.0.can_reopen', false));
    actingAs($ben)->post(route('reopenings.store'), ['ids' => [$approved->id]])->assertForbidden();

    expect($approved->refresh()->state)->toBe(WorkEntryState::Approved);
});

it('reopens only for the Reports a reopen Scope of reports covers', function () {
    $rae = reopener();
    $report = WorkEntry::factory()->for(assignmentOf(Employee::factory()->reportingTo($rae)->create()))->approved()->create(['date' => '2026-11-10']);
    $peer = WorkEntry::factory()->for(assignmentOf(Employee::factory()->create(['manager_id' => $rae->manager_id])))->approved()->create(['date' => '2026-11-10']);

    actingAs($rae)->post(route('reopenings.store'), ['ids' => [$report->id, $peer->id]])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => '1 Work Entry reopened, 1 skipped.']);

    expect($report->refresh()->state)->toBe(WorkEntryState::Pending)
        ->and($peer->refresh()->state)->toBe(WorkEntryState::Approved);
});

it('never lets a reopen Role approve beyond the Employees one is the Manager of', function () {
    $ada = Employee::factory()->create();
    $reopener = employeeWith([Permission::ReopenWorkEntries->value => Scope::Everyone], ['manager_id' => $ada->id]);
    Employee::factory()->reportingTo($reopener)->create();
    $pending = WorkEntry::factory()->for(assignmentOf(Employee::factory()->reportingTo($ada)->create()))->create(['date' => '2026-11-10']);

    actingAs($reopener)->post(route('approvals.store'), ['ids' => [$pending->id]])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'No Work Entries approved, 1 skipped.']);

    expect($pending->refresh()->state)->toBe(WorkEntryState::Pending);
});

it('refuses a reopening selection that is not a list of Work Entry IDs', function () {
    $rae = reopener();

    actingAs($rae)->post(route('reopenings.store'), ['ids' => []])
        ->assertInvalid(['ids' => 'Select at least one Work Entry.']);
});

it('shows the Work Entries list, not Approvals, to those who may only reopen Work Entries', function () {
    $rae = reopener();

    actingAs($rae)->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.canSeeWorkEntryList', true)
            ->where('auth.canSeeApprovals', false));
});
