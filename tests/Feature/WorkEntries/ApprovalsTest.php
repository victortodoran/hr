<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Enums\WorkEntryState;
use App\Http\Controllers\ApprovalController;
use App\Http\Requests\ApprovalFiltersRequest;
use App\Http\Requests\SelectedWorkEntriesRequest;
use App\Models\Employee;
use App\Models\WorkEntry;
use App\Policies\WorkEntryPolicy;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(ApprovalController::class, WorkEntryPolicy::class, Employee::class, ApprovalFiltersRequest::class, SelectedWorkEntriesRequest::class);

beforeEach(function () {
    travelTo('2026-11-11 09:00');
});

/**
 * Ben, below Ada at the top, who approves the Work Entries of the Employees
 * he is the Manager of. No Role is involved.
 */
function approver(): Employee
{
    $ada = Employee::factory()->top()->create(['name' => 'Ada']);

    return Employee::factory()->create(['name' => 'Ben', 'manager_id' => $ada->id]);
}

/**
 * An approver who is already some Employee's Manager, so the Approvals page
 * is open to them.
 */
function managingApprover(): Employee
{
    $ben = approver();
    Employee::factory()->create(['manager_id' => $ben->id]);

    return $ben;
}

it('lists the Pending Work Entries of the approver\'s Direct Reports, oldest first, but not of Reports further down', function () {
    $ben = approver();
    $cara = Employee::factory()->create(['name' => 'Cara', 'manager_id' => $ben->id]);
    $dev = Employee::factory()->create(['name' => 'Dev', 'manager_id' => $cara->id]);
    $carasRelaunch = assignmentOf($cara);
    $later = WorkEntry::factory()->for($carasRelaunch)->create(['date' => '2026-11-10', 'minutes' => 90, 'description' => 'API']);
    $earlier = WorkEntry::factory()->for($carasRelaunch)->create(['date' => '2026-11-03', 'minutes' => 60, 'description' => 'Specs']);
    $devsBilling = assignmentOf($dev, 'Billing');
    WorkEntry::factory()->for($devsBilling)->create(['date' => '2026-11-04']);
    WorkEntry::factory()->for($carasRelaunch)->approved()->create(['date' => '2026-11-02']);
    $outsiders = assignmentOf(Employee::factory()->create(['manager_id' => $ben->manager_id]), 'Archive');
    WorkEntry::factory()->for($outsiders)->create(['date' => '2026-11-04']);

    $response = actingAs($ben)->get(route('approvals.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('approvals/Index')
        ->where('tab', 'work-entries')
        ->where('waiting', ['workEntries' => 2, 'timeOff' => 0])
        ->missing('toApprove')
        ->missing('canReopen')
        ->missing('canLock')
        ->where('filters', ['employee' => $cara->id, 'project' => null])
        ->where('entries.data', [
            ['id' => $earlier->id, 'date' => '2026-11-03', 'employee' => ['id' => $cara->id, 'name' => 'Cara'], 'project' => 'Relaunch', 'hours' => 1, 'description' => 'Specs'],
            ['id' => $later->id, 'date' => '2026-11-10', 'employee' => ['id' => $cara->id, 'name' => 'Cara'], 'project' => 'Relaunch', 'hours' => 1.5, 'description' => 'API'],
        ])
        ->where('total', 2.5)
        ->where('employees', [['id' => $cara->id, 'name' => 'Cara', 'pending' => 2]])
        ->where('projects', [['id' => $carasRelaunch->project_id, 'name' => 'Relaunch']]));
});

it('never lists the approver\'s own Work Entries, nor their Manager\'s', function () {
    $ben = approver();
    $cara = Employee::factory()->create(['name' => 'Cara', 'manager_id' => $ben->id]);
    $caras = WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-10']);
    WorkEntry::factory()->for(assignmentOf($ben, 'Billing'))->create(['date' => '2026-11-10']);
    WorkEntry::factory()->for(assignmentOf(Employee::query()->findOrFail($ben->manager_id), 'Archive'))->create(['date' => '2026-11-10']);

    $response = actingAs($ben)->get(route('approvals.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('entries.data', fn (Collection $data): bool => $data->pluck('id')->all() === [$caras->id])
        ->where('employees', [['id' => $cara->id, 'name' => 'Cara', 'pending' => 1]]));
});

it('lets the next Manager up approve for a Manager who has left', function () {
    $ben = approver();
    $fay = Employee::factory()->former()->create(['manager_id' => $ben->id]);
    $pending = WorkEntry::factory()->for(assignmentOf(Employee::factory()->create(['manager_id' => $fay->id])))->create(['date' => '2026-11-10']);

    actingAs($ben)->post(route('approvals.store'), ['ids' => [$pending->id]])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => '1 Work Entry approved.']);

    expect($pending->refresh()->state)->toBe(WorkEntryState::Approved);
});

it('lets a Manager approve the remaining Work Entries of a Direct Report who has left, and open the page for them', function () {
    $ben = approver();
    $pending = WorkEntry::factory()->for(assignmentOf(Employee::factory()->former()->create(['manager_id' => $ben->id])))->create(['date' => '2026-11-01']);

    actingAs($ben)->get(route('approvals.index'))
        ->assertInertia(fn (Assert $page) => $page->where('entries.data.0.id', $pending->id));
    actingAs($ben)->post(route('approvals.store'), ['ids' => [$pending->id]]);

    expect($pending->refresh()->state)->toBe(WorkEntryState::Approved);
});

it('opens Work Entries for a tab it doesn\'t know', function () {
    actingAs(managingApprover())->get(route('approvals.index', ['tab' => 'payroll']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'work-entries')
            ->has('entries'));
});

it('refuses the Approvals page to someone who manages nobody, however their Roles let them see, reopen or lock Work Entries', function () {
    $cara = employeeWith([
        Permission::ManageWorkEntries->value => Scope::Everyone,
        Permission::ViewWorkEntries->value => Scope::Everyone,
        Permission::ReopenWorkEntries->value => Scope::Everyone,
        Permission::LockWorkEntries->value => null,
    ]);

    actingAs($cara)->get(route('approvals.index'))->assertForbidden();
    actingAs($cara)->post(route('approvals.store'), ['ids' => []])->assertForbidden();
});

/**
 * Ben approving for Cara and Dev, who will have Pending Work Entries, and
 * Bea, who has only Approved ones.
 *
 * @return array{ben: Employee, cara: Employee, dev: Employee, bea: Employee}
 */
function approverOfCaraAndDev(): array
{
    $ben = approver();
    $dev = Employee::factory()->create(['name' => 'Dev', 'manager_id' => $ben->id]);
    $cara = Employee::factory()->create(['name' => 'Cara', 'manager_id' => $ben->id]);
    $bea = Employee::factory()->create(['name' => 'Bea', 'manager_id' => $ben->id]);
    WorkEntry::factory()->for(assignmentOf($bea, 'Archive'))->approved()->create(['date' => '2026-11-03']);

    return ['ben' => $ben, 'cara' => $cara, 'dev' => $dev, 'bea' => $bea];
}

it('opens on the first Employee by name with Pending Work Entries, offering only those, each with how many', function () {
    ['ben' => $ben, 'cara' => $cara, 'dev' => $dev] = approverOfCaraAndDev();
    $carasRelaunch = assignmentOf($cara);
    $caras = WorkEntry::factory()->for($carasRelaunch)->count(2)->create(['date' => '2026-11-03']);
    WorkEntry::factory()->for(assignmentOf($dev, 'Billing'))->create(['date' => '2026-11-02']);

    $response = actingAs($ben)->get(route('approvals.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('filters', ['employee' => $cara->id, 'project' => null])
        ->where('employees', [
            ['id' => $cara->id, 'name' => 'Cara', 'pending' => 2],
            ['id' => $dev->id, 'name' => 'Dev', 'pending' => 1],
        ])
        ->where('projects', [['id' => $carasRelaunch->project_id, 'name' => 'Relaunch']])
        ->where('entries.data', fn (Collection $data): bool => $data->pluck('id')->all() === $caras->pluck('id')->all()));
});

it('shows only the chosen Employee\'s Pending Work Entries, offering only the Projects they have Pending Work Entries on', function () {
    ['ben' => $ben, 'cara' => $cara, 'dev' => $dev] = approverOfCaraAndDev();
    WorkEntry::factory()->for(assignmentOf($cara, 'Archive'))->create(['date' => '2026-11-03']);
    $devsBilling = assignmentOf($dev, 'Billing');
    $devsRelaunch = assignmentOf($dev, 'Relaunch');
    $later = WorkEntry::factory()->for($devsBilling)->create(['date' => '2026-11-09', 'minutes' => 120]);
    $earlier = WorkEntry::factory()->for($devsRelaunch)->create(['date' => '2026-11-04', 'minutes' => 60]);
    WorkEntry::factory()->for($devsBilling)->approved()->create(['date' => '2026-11-05']);
    WorkEntry::factory()->for(assignmentOf($dev, 'Website'))->approved()->create(['date' => '2026-11-05']);

    $response = actingAs($ben)->get(route('approvals.index', ['employee' => $dev->id]));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('filters', ['employee' => $dev->id, 'project' => null])
        ->where('projects', [
            ['id' => $devsBilling->project_id, 'name' => 'Billing'],
            ['id' => $devsRelaunch->project_id, 'name' => 'Relaunch'],
        ])
        ->where('entries.data', fn (Collection $data): bool => $data->pluck('id')->all() === [$earlier->id, $later->id])
        ->where('total', 3));
});

it('narrows the chosen Employee\'s Pending Work Entries to a Project, keeping the total to what matches', function () {
    ['ben' => $ben, 'dev' => $dev] = approverOfCaraAndDev();
    $devsBilling = assignmentOf($dev, 'Billing');
    $billing = WorkEntry::factory()->for($devsBilling)->create(['date' => '2026-11-09', 'minutes' => 120]);
    WorkEntry::factory()->for(assignmentOf($dev, 'Relaunch'))->create(['date' => '2026-11-04', 'minutes' => 60]);

    $response = actingAs($ben)->get(route('approvals.index', ['employee' => $dev->id, 'project' => $devsBilling->project_id]));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('filters', ['employee' => $dev->id, 'project' => $devsBilling->project_id])
        ->where('entries.data', fn (Collection $data): bool => $data->pluck('id')->all() === [$billing->id])
        ->where('total', 2));
});

it('falls back to the first Employee by name when asked for one with nothing waiting for the approver', function (string $asked) {
    ['ben' => $ben, 'cara' => $cara, 'dev' => $dev, 'bea' => $bea] = approverOfCaraAndDev();
    WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-03']);
    WorkEntry::factory()->for(assignmentOf($dev, 'Billing'))->create(['date' => '2026-11-03']);
    WorkEntry::factory()->for(assignmentOf($ben, 'Website'))->create(['date' => '2026-11-03']);
    $outsider = Employee::factory()->create(['manager_id' => $ben->manager_id]);
    WorkEntry::factory()->for(assignmentOf($outsider, 'Archive'))->create(['date' => '2026-11-03']);
    $employee = match ($asked) {
        'nothing waiting' => $bea->id,
        'not approved by the approver' => $outsider->id,
        'the approver' => $ben->id,
        'unknown' => Employee::query()->max('id') + 1,
        default => '',
    };

    $response = actingAs($ben)->get(route('approvals.index', ['employee' => $employee]));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('filters.employee', $cara->id)
        ->where('entries.data', fn (Collection $data): bool => $data->pluck('employee.id')->all() === [$cara->id]));
})->with(['nothing waiting', 'not approved by the approver', 'the approver', 'unknown', 'none']);

it('treats a Project the Employee has nothing waiting on as any Project', function () {
    ['ben' => $ben, 'cara' => $cara, 'dev' => $dev] = approverOfCaraAndDev();
    $caras = WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-03']);
    $devsBilling = assignmentOf($dev, 'Billing');
    WorkEntry::factory()->for($devsBilling)->create(['date' => '2026-11-03']);

    $response = actingAs($ben)->get(route('approvals.index', ['employee' => $cara->id, 'project' => $devsBilling->project_id]));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('filters', ['employee' => $cara->id, 'project' => null])
        ->where('entries.data', fn (Collection $data): bool => $data->pluck('id')->all() === [$caras->id]));
});

it('moves on to the next Employee once the chosen one\'s Work Entries are all approved', function () {
    ['ben' => $ben, 'cara' => $cara, 'dev' => $dev] = approverOfCaraAndDev();
    $caras = WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-03']);
    WorkEntry::factory()->for(assignmentOf($dev, 'Billing'))->create(['date' => '2026-11-03']);

    actingAs($ben)->post(route('approvals.store'), ['ids' => [$caras->id]]);
    $response = actingAs($ben)->get(route('approvals.index', ['employee' => $cara->id]));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('filters', ['employee' => $dev->id, 'project' => null])
        ->where('employees', [['id' => $dev->id, 'name' => 'Dev', 'pending' => 1]]));
});

it('offers no Employee or Project to choose when nothing waits for the approver', function () {
    ['ben' => $ben] = approverOfCaraAndDev();

    $response = actingAs($ben)->get(route('approvals.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('filters', ['employee' => null, 'project' => null])
        ->where('employees', [])
        ->where('projects', [])
        ->where('entries.data', [])
        ->where('total', 0));
});

it('has no date range to filter by', function () {
    ['ben' => $ben, 'cara' => $cara] = approverOfCaraAndDev();
    $caras = WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-03']);

    $response = actingAs($ben)->get(route('approvals.index', ['from' => '2026-11-04', 'to' => 'later']));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('filters', ['employee' => $cara->id, 'project' => null])
        ->where('entries.data', fn (Collection $data): bool => $data->pluck('id')->all() === [$caras->id]));
});

it('refuses malformed filters', function (array $filters, string $field, string $message) {
    $ben = managingApprover();

    actingAs($ben)->get(route('approvals.index', $filters))->assertInvalid([$field => $message]);
})->with([
    'Employee not a number' => [['employee' => 'Cara'], 'employee', 'The employee field must be an integer.'],
    'Project not a number' => [['project' => 'Relaunch'], 'project', 'The project field must be an integer.'],
]);

it('pages through 25 Work Entries at a time, keeping the filters', function () {
    $ben = approver();
    $relaunch = assignmentOf(Employee::factory()->create(['manager_id' => $ben->id]));
    WorkEntry::factory()->for($relaunch)->count(26)->create(['date' => '2026-11-10', 'minutes' => 15]);

    $response = actingAs($ben)->get(route('approvals.index', ['project' => $relaunch->project_id]));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('entries.data', 25)
        ->where('entries.total', 26)
        ->where('entries.next_page_url', route('approvals.index', ['project' => $relaunch->project_id, 'page' => 2]))
        ->where('total', 6.5));
});

it('gives every matching Work Entry\'s ID when asked, for selecting them all', function () {
    $ben = approver();
    $relaunch = assignmentOf(Employee::factory()->create(['manager_id' => $ben->id]));
    $ids = WorkEntry::factory()->for($relaunch)->count(26)->create(['date' => '2026-11-10'])->pluck('id')->all();
    WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2026-11-10']);

    actingAs($ben)->get(route('approvals.index'))
        ->assertInertia(fn (Assert $page) => $page->missing('matchingIds'));
    actingAs($ben)->get(route('approvals.index'))
        ->assertInertia(fn (Assert $page) => $page->reloadOnly('matchingIds', fn (Assert $reload) => $reload->where('matchingIds', $ids)));
});

it('approves the selected Pending Work Entries, recording who approved them and when', function () {
    $ben = approver();
    $relaunch = assignmentOf(Employee::factory()->create(['manager_id' => $ben->id]));
    $first = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-09']);
    $second = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10']);
    $untouched = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10']);

    $response = actingAs($ben)->from(route('approvals.index', ['project' => $relaunch->project_id]))
        ->post(route('approvals.store'), ['ids' => [(string) $first->id, (string) $second->id]]);

    $response->assertRedirect(route('approvals.index', ['project' => $relaunch->project_id]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => '2 Work Entries approved.']);
    foreach ([$first, $second] as $workEntry) {
        $workEntry->refresh();
        expect($workEntry->state)->toBe(WorkEntryState::Approved)
            ->and($workEntry->approved_by)->toBe($ben->id)
            ->and($workEntry->approved_at?->toDateTimeString())->toBe('2026-11-11 09:00:00');
    }
    expect($untouched->refresh()->state)->toBe(WorkEntryState::Pending);
});

it('approves only the selected Work Entries it may, counting each once, and says how many it skipped', function () {
    $ben = approver();
    $relaunch = assignmentOf(Employee::factory()->create(['manager_id' => $ben->id]));
    $pending = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10']);
    $own = WorkEntry::factory()->for(assignmentOf($ben))->create(['date' => '2026-11-10']);
    $approved = WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2026-11-10']);
    $locked = WorkEntry::factory()->for($relaunch)->locked()->create(['date' => '2026-11-10']);
    $approvedBy = $approved->approved_by;

    $response = actingAs($ben)->post(route('approvals.store'), ['ids' => [$pending->id, $pending->id, $own->id, $approved->id, $locked->id, WorkEntry::query()->max('id') + 1]]);

    $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => '1 Work Entry approved, 4 skipped.']);
    expect($pending->refresh()->state)->toBe(WorkEntryState::Approved)
        ->and($own->refresh()->state)->toBe(WorkEntryState::Pending)
        ->and($approved->refresh()->approved_by)->toBe($approvedBy)
        ->and($locked->refresh()->state)->toBe(WorkEntryState::Locked);
});

it('skips Work Entries of Employees the approver is not the Manager of, Reports further down included', function () {
    $ben = approver();
    $cara = Employee::factory()->create(['manager_id' => $ben->id]);
    $outside = WorkEntry::factory()->for(assignmentOf(Employee::factory()->create()))->create(['date' => '2026-11-10']);
    $below = WorkEntry::factory()->for(assignmentOf(Employee::factory()->create(['manager_id' => $cara->id])))->create(['date' => '2026-11-10']);

    $response = actingAs($ben)->post(route('approvals.store'), ['ids' => [$outside->id, $below->id]]);

    $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'No Work Entries approved, 2 skipped.']);
    expect($outside->refresh()->state)->toBe(WorkEntryState::Pending)
        ->and($below->refresh()->state)->toBe(WorkEntryState::Pending);
});

it('refuses a selection that is not a list of Work Entry IDs', function (mixed $ids, string $field, string $message) {
    $ben = managingApprover();

    actingAs($ben)->post(route('approvals.store'), ['ids' => $ids])->assertInvalid([$field => $message]);
})->with([
    'missing' => [null, 'ids', 'Select at least one Work Entry.'],
    'empty' => [[], 'ids', 'Select at least one Work Entry.'],
    'not a list' => ['all', 'ids', 'Select at least one Work Entry.'],
    'not numbers' => [['first'], 'ids.0', 'The ids.0 field must be an integer.'],
]);

it('shows the Approvals item in the navigation to Managers only, not to someone who only manages, reopens or locks Work Entries', function () {
    $ben = approver();
    Employee::factory()->create(['manager_id' => $ben->id]);
    $cara = employeeWith([
        Permission::ManageWorkEntries->value => Scope::Everyone,
        Permission::ReopenWorkEntries->value => Scope::Everyone,
        Permission::LockWorkEntries->value => null,
    ]);

    actingAs($ben)->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canSeeApprovals', true));
    actingAs($cara)->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canSeeApprovals', false));
});
