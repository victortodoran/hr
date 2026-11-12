<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Enums\WorkEntryState;
use App\Http\Controllers\ApprovalController;
use App\Models\Employee;
use App\Models\WorkEntry;
use App\Policies\WorkEntryPolicy;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(ApprovalController::class, WorkEntryPolicy::class, Employee::class);

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
    $ada = Employee::factory()->top()->create(['name' => 'Ada']);

    return employeeWith($grants ?? [Permission::ReopenWorkEntries->value => Scope::Reports], ['name' => 'Rae', 'manager_id' => $ada->id]);
}

it('opens Approvals on the Approved Work Entries of the Employees someone who manages nobody may reopen for', function () {
    $rae = reopener([Permission::ReopenWorkEntries->value => Scope::Everyone]);
    $ada = Employee::query()->findOrFail($rae->manager_id);
    $cara = Employee::factory()->create(['name' => 'Cara', 'manager_id' => $ada->id]);
    $relaunch = assignmentOf($cara);
    $ben = Employee::factory()->create(['name' => 'Ben']);
    $approved = WorkEntry::factory()->for($relaunch)->approved($ben)->create(['date' => '2026-11-10', 'minutes' => 60, 'description' => 'API', 'approved_at' => '2026-11-10 17:00']);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10']);
    WorkEntry::factory()->for(assignmentOf($rae, 'Archive'))->approved($ben)->create(['date' => '2026-11-10']);

    $response = actingAs($rae)->get(route('approvals.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('filters.state', 'approved')
        ->where('canApprove', false)
        ->where('canReopen', true)
        ->where('entries.data', [
            ['id' => $approved->id, 'date' => '2026-11-10', 'employee' => ['id' => $cara->id, 'name' => 'Cara'], 'project' => 'Relaunch', 'hours' => 1, 'description' => 'API', 'state' => 'approved', 'approved' => ['by' => 'Ben', 'on' => '2026-11-10'], 'locked' => null, 'can_approve' => false, 'can_reopen' => true, 'can_lock' => false],
        ])
        ->where('employees', [['id' => $ada->id, 'name' => 'Ada'], ['id' => $ben->id, 'name' => 'Ben'], ['id' => $cara->id, 'name' => 'Cara']]));
});

it('offers to approve only for the Manager\'s Direct Reports, and to reopen for everyone a reopen Scope covers', function () {
    $ada = Employee::factory()->top()->create(['name' => 'Ada']);
    $rae = employeeWith([Permission::ReopenWorkEntries->value => Scope::Everyone], ['name' => 'Rae', 'manager_id' => $ada->id]);
    $cara = Employee::factory()->create(['name' => 'Cara', 'manager_id' => $rae->id]);
    $carasPending = WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-10']);
    $adasPending = WorkEntry::factory()->for(assignmentOf($ada))->create(['date' => '2026-11-10']);
    $adasApproved = WorkEntry::factory()->for(assignmentOf($ada))->approved($rae)->create(['date' => '2026-11-10']);

    actingAs($rae)->get(route('approvals.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.state', 'pending')
            ->where('canApprove', true)
            ->where('canReopen', true)
            ->where('entries.data', fn (Collection $data): bool => $data->map(fn (array $row): array => [$row['id'], $row['can_approve'], $row['can_reopen']])->all() === [
                [$carasPending->id, true, false],
                [$adasPending->id, false, false],
            ])
            ->where('employees', [['id' => $ada->id, 'name' => 'Ada'], ['id' => $cara->id, 'name' => 'Cara']]));
    actingAs($rae)->get(route('approvals.index', ['state' => 'approved']))
        ->assertInertia(fn (Assert $page) => $page->where('entries.data.0.id', $adasApproved->id)->where('entries.data.0.can_reopen', true));
});

it('reopens the selected Approved Work Entries, withdrawing their approval', function () {
    $rae = reopener();
    $relaunch = assignmentOf(Employee::factory()->create(['manager_id' => $rae->id]));
    $first = WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2026-11-09']);
    $second = WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2026-11-10']);
    $untouched = WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2026-11-10']);

    $response = actingAs($rae)->from(route('approvals.index', ['state' => 'approved']))
        ->delete(route('approvals.destroy'), ['ids' => [$first->id, $second->id]]);

    $response->assertRedirect(route('approvals.index', ['state' => 'approved']))
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
    $relaunch = assignmentOf(Employee::factory()->create(['manager_id' => $rae->id]));
    $approved = WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2026-11-10']);
    $own = WorkEntry::factory()->for(assignmentOf($rae))->approved()->create(['date' => '2026-11-10']);
    $pending = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10']);
    $locked = WorkEntry::factory()->for($relaunch)->locked()->create(['date' => '2026-11-10']);

    $response = actingAs($rae)->delete(route('approvals.destroy'), ['ids' => [$approved->id, $approved->id, $own->id, $pending->id, $locked->id]]);

    $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => '1 Work Entry reopened, 3 skipped.']);
    expect($approved->refresh()->state)->toBe(WorkEntryState::Pending)
        ->and($own->refresh()->state)->toBe(WorkEntryState::Approved)
        ->and($pending->refresh()->state)->toBe(WorkEntryState::Pending)
        ->and($locked->refresh()->state)->toBe(WorkEntryState::Locked);
});

it('lets a Manager reopen their Direct Report\'s Work Entries with no Role, while a reopen Role never lets anyone approve', function () {
    $ada = Employee::factory()->top()->create();
    $manager = Employee::factory()->create(['manager_id' => $ada->id]);
    $reopener = employeeWith([Permission::ReopenWorkEntries->value => Scope::Everyone], ['manager_id' => $ada->id]);
    $relaunch = assignmentOf(Employee::factory()->create(['manager_id' => $manager->id]));
    $approved = WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2026-11-10']);
    $pending = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10']);

    actingAs($manager)->delete(route('approvals.destroy'), ['ids' => [$approved->id]])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => '1 Work Entry reopened.']);
    actingAs($reopener)->post(route('approvals.store'), ['ids' => [$pending->id]])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'No Work Entries approved, 1 skipped.']);

    expect($approved->refresh()->state)->toBe(WorkEntryState::Pending)
        ->and($pending->refresh()->state)->toBe(WorkEntryState::Pending);
});

it('refuses reopening to a Manager two levels up who holds no reopen Role', function () {
    $ada = Employee::factory()->top()->create();
    $ben = Employee::factory()->create(['manager_id' => $ada->id]);
    $approved = WorkEntry::factory()->for(assignmentOf(Employee::factory()->create(['manager_id' => $ben->id])))->approved()->create(['date' => '2026-11-10']);

    actingAs($ada)->delete(route('approvals.destroy'), ['ids' => [$approved->id]])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'No Work Entries reopened, 1 skipped.']);

    expect($approved->refresh()->state)->toBe(WorkEntryState::Approved);
});

it('refuses a reopening selection that is not a list of Work Entry IDs', function () {
    $rae = reopener();

    actingAs($rae)->delete(route('approvals.destroy'), ['ids' => []])
        ->assertInvalid(['ids' => 'Select at least one Work Entry.']);
});

it('shows the Approvals item to those who may reopen Work Entries', function () {
    $rae = reopener();

    actingAs($rae)->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canSeeApprovals', true));
});
