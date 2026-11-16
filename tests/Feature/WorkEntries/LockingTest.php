<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Enums\WorkEntryState;
use App\Http\Controllers\WorkEntries\LockController;
use App\Http\Controllers\WorkEntries\WorkEntryListController;
use App\Http\Requests\WorkEntries\LockPreviewRequest;
use App\Http\Requests\WorkEntries\SelectedWorkEntriesRequest;
use App\Http\Requests\WorkEntries\WorkEntryFiltersRequest;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\WorkEntry;
use App\Policies\WorkEntryPolicy;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(LockController::class, WorkEntryListController::class, WorkEntryPolicy::class, WorkEntryFiltersRequest::class, LockPreviewRequest::class, SelectedWorkEntriesRequest::class, WorkEntry::class);

beforeEach(function () {
    travelTo('2026-11-11 09:00');
});

/**
 * Finn, who locks Work Entries company-wide and nothing else.
 */
function locker(): Employee
{
    return employeeWith([Permission::LockWorkEntries->value => null], ['name' => 'Finn']);
}

it('lists everyone\'s Work Entries, the locker\'s own included, offering to lock the Approved ones, for someone who can only lock', function () {
    $finn = locker();
    $cara = Employee::factory()->create(['name' => 'Cara']);
    $ben = Employee::factory()->create(['name' => 'Ben']);
    $carasApproved = WorkEntry::factory()->for(assignmentOf($cara))->approved($ben)->create(['date' => '2026-11-09']);
    $ownApproved = WorkEntry::factory()->for(assignmentOf($finn))->approved($ben)->create(['date' => '2026-11-10']);
    $carasPending = WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-10']);

    $response = actingAs($finn)->get(route('work-entry-list.index', ['employee' => 'all']));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('filters.state', null)
        ->where('canReopen', false)
        ->where('canLock', true)
        ->where('entries.data', fn (Collection $data): bool => $data->map(fn (array $row): array => [$row['id'], $row['can_reopen'], $row['can_lock']])->all() === [
            [$carasApproved->id, false, true],
            [$ownApproved->id, false, true],
            [$carasPending->id, false, false],
        ])
        ->where('employees', [['id' => $cara->id, 'name' => 'Cara', 'count' => 2], ['id' => $finn->id, 'name' => 'Finn', 'count' => 1]]));
});

it('offers to lock only Approved Work Entries', function () {
    $finn = locker();
    $relaunch = assignmentOf(Employee::factory()->reportingTo($finn)->create());
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10']);
    WorkEntry::factory()->for($relaunch)->locked($finn)->create(['date' => '2026-11-10']);

    actingAs($finn)->get(route('work-entry-list.index', ['state' => 'pending']))
        ->assertInertia(fn (Assert $page) => $page->where('entries.data.0.can_lock', false));
    actingAs($finn)->get(route('work-entry-list.index', ['state' => 'locked']))
        ->assertInertia(fn (Assert $page) => $page->where('entries.data.0.can_lock', false));
});

it('sums up the selected Approved Work Entries by Project and Employee, warning of Pending ones in the same month and Project whatever state is shown', function () {
    $finn = locker();
    $cara = Employee::factory()->create(['name' => 'Cara']);
    $dev = Employee::factory()->create(['name' => 'Dev']);
    $carasRelaunch = assignmentOf($cara);
    $devsRelaunch = Assignment::factory()->for($dev)->for($carasRelaunch->project)->create(['start_date' => '2026-11-02', 'end_date' => null]);
    $devsBilling = assignmentOf($dev, 'Billing');
    $selected = [
        WorkEntry::factory()->for($devsRelaunch)->approved()->create(['date' => '2026-11-04', 'minutes' => 90]),
        WorkEntry::factory()->for($carasRelaunch)->approved()->create(['date' => '2026-11-03', 'minutes' => 120]),
        WorkEntry::factory()->for($carasRelaunch)->approved()->create(['date' => '2026-11-04', 'minutes' => 60]),
        WorkEntry::factory()->for($devsBilling)->approved()->create(['date' => '2026-11-05', 'minutes' => 30]),
        WorkEntry::factory()->for($carasRelaunch)->create(['date' => '2026-11-05', 'minutes' => 45]),
        WorkEntry::factory()->for($carasRelaunch)->locked()->create(['date' => '2026-11-05', 'minutes' => 15]),
    ];
    WorkEntry::factory()->for($devsRelaunch)->create(['date' => '2026-11-06', 'minutes' => 75]);
    WorkEntry::factory()->for($devsRelaunch)->create(['date' => '2026-10-30', 'minutes' => 600]);
    WorkEntry::factory()->for($devsBilling)->create(['date' => '2026-11-06', 'minutes' => 600]);

    $response = actingAs($finn)->getJson(route('locks.create', [
        'ids' => collect($selected)->pluck('id')->all(),
        'month' => '2026-11',
        'project' => $carasRelaunch->project_id,
        'state' => 'approved',
    ]));

    $response->assertOk()->assertExactJson([
        'locking' => ['count' => 4, 'hours' => 5],
        'breakdown' => [
            ['project' => 'Billing', 'employee' => 'Dev', 'hours' => 0.5],
            ['project' => 'Relaunch', 'employee' => 'Cara', 'hours' => 3],
            ['project' => 'Relaunch', 'employee' => 'Dev', 'hours' => 1.5],
        ],
        'pending' => ['count' => 2, 'hours' => 2],
    ]);
    actingAs($finn)->getJson(route('locks.create', ['ids' => [$selected[0]->id], 'month' => '2026-11', 'employee' => $dev->id]))
        ->assertJsonPath('pending', ['count' => 2, 'hours' => 11.25]);
    actingAs($finn)->getJson(route('locks.create', ['ids' => [$selected[0]->id], 'week' => '2026-11-06', 'employee' => $dev->id]))
        ->assertJsonPath('pending', ['count' => 2, 'hours' => 11.25]);
    actingAs($finn)->getJson(route('locks.create', ['ids' => [$selected[0]->id], 'from' => '2026-10-30', 'to' => '2026-11-05', 'employee' => $dev->id]))
        ->assertJsonPath('pending', ['count' => 1, 'hours' => 10]);
});

it('locks the selected Work Entries that are still Approved, the locker\'s own included, recording who locked them and when', function () {
    $finn = locker();
    $relaunch = assignmentOf(Employee::factory()->create());
    $approved = WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2026-11-09']);
    $own = WorkEntry::factory()->for(assignmentOf($finn))->approved()->create(['date' => '2026-11-10']);
    $pending = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10']);
    $locked = WorkEntry::factory()->for($relaunch)->locked()->create(['date' => '2026-11-10']);
    $notSelected = WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2026-11-10']);
    $lockedBy = $locked->locked_by;

    $response = actingAs($finn)->from(route('approvals.index', ['state' => 'approved']))
        ->post(route('locks.store'), ['ids' => [$approved->id, $own->id, $pending->id, $locked->id, $approved->id]]);

    $response->assertRedirect(route('approvals.index', ['state' => 'approved']))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => '2 Work Entries locked, 2 skipped.']);
    foreach ([$approved, $own] as $workEntry) {
        $workEntry->refresh();
        expect($workEntry->state)->toBe(WorkEntryState::Locked)
            ->and($workEntry->locked_by)->toBe($finn->id)
            ->and($workEntry->locked_at?->toDateTimeString())->toBe('2026-11-11 09:00:00');
    }
    expect($pending->refresh()->state)->toBe(WorkEntryState::Pending)
        ->and($locked->refresh()->locked_by)->toBe($lockedBy)
        ->and($notSelected->refresh()->state)->toBe(WorkEntryState::Approved);
});

it('refuses malformed filters on the lock summary', function () {
    $finn = locker();
    $approved = WorkEntry::factory()->for(assignmentOf(Employee::factory()->create()))->approved()->create(['date' => '2026-11-09']);

    actingAs($finn)->getJson(route('locks.create', ['ids' => [$approved->id], 'month' => 'soon']))
        ->assertJsonValidationErrors(['month' => 'The month field must match the format Y-m.']);
});

it('says when a single Work Entry is locked', function () {
    $finn = locker();
    $approved = WorkEntry::factory()->for(assignmentOf(Employee::factory()->create()))->approved()->create(['date' => '2026-11-09']);

    actingAs($finn)->post(route('locks.store'), ['ids' => [$approved->id]])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => '1 Work Entry locked.']);
});

it('refuses locking and its summary to someone who can\'t lock', function () {
    $ben = employeeWith([Permission::ReopenWorkEntries->value => Scope::Everyone]);
    $approved = WorkEntry::factory()->for(assignmentOf(Employee::factory()->create()))->approved()->create(['date' => '2026-11-09']);

    actingAs($ben)->getJson(route('locks.create', ['ids' => [$approved->id]]))->assertForbidden();
    actingAs($ben)->post(route('locks.store'), ['ids' => [$approved->id]])->assertForbidden();
    actingAs($ben)->get(route('work-entry-list.index'))->assertInertia(fn (Assert $page) => $page->where('canLock', false));

    expect($approved->refresh()->state)->toBe(WorkEntryState::Approved);
});

it('refuses a lock selection that is not a list of Work Entry IDs', function () {
    $finn = locker();

    actingAs($finn)->getJson(route('locks.create'))
        ->assertJsonValidationErrors(['ids' => 'Select at least one Work Entry.']);
    actingAs($finn)->post(route('locks.store'), ['ids' => []])
        ->assertInvalid(['ids' => 'Select at least one Work Entry.']);
});

it('shows the Work Entries list, not Approvals, to those who can only lock', function () {
    actingAs(locker())->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.canSeeWorkEntryList', true)
            ->where('auth.canSeeApprovals', false));
});
