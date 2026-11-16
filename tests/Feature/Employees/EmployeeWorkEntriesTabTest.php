<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\EmployeeController;
use App\Models\Employee;
use App\Models\WorkEntry;
use App\Policies\EmployeePolicy;
use App\Policies\WorkEntryPolicy;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\travelTo;

covers(EmployeeController::class, EmployeePolicy::class, WorkEntryPolicy::class);

beforeEach(function () {
    travelTo('2026-11-11 09:00');
});

it('lists only the page\'s Employee\'s Work Entries, this month by default, oldest first, even to a viewer who sees everyone\'s', function () {
    $ada = Employee::factory()->top()->create();
    $cara = Employee::factory()->create(['manager_id' => $ada->id]);
    $dan = Employee::factory()->create(['manager_id' => $ada->id]);
    $relaunch = assignmentOf($cara, 'Relaunch', ['start_date' => '2026-10-01']);
    $later = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10', 'minutes' => 120]);
    $earlier = WorkEntry::factory()->for($relaunch)->approved($ada)->create(['date' => '2026-11-02', 'minutes' => 180]);
    WorkEntry::factory()->for($relaunch)->declined($ada, 'Wrong Project')->create(['date' => '2026-11-03', 'minutes' => 60]);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-10-30']);
    WorkEntry::factory()->for(assignmentOf($dan, 'Relaunch'))->create(['date' => '2026-11-10']);
    $hr = employeeWith([Permission::ViewEmployeeRecords->value => Scope::Everyone, Permission::ViewWorkEntries->value => Scope::Everyone]);

    actingAs($hr)->get(route('employees.show', ['employee' => $cara, 'tab' => 'work-entries']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'work-entries')
            ->where('tabs', ['overview', 'work-entries'])
            ->where('workEntries.filters', ['period' => ['mode' => 'month', 'from' => '2026-11-01', 'to' => '2026-11-30', 'query' => ['month' => '2026-11']], 'project' => null, 'state' => null])
            ->where('workEntries.entries.data', fn (Collection $data): bool => $data->pluck('id')->all() === [$earlier->id, $data[1]['id'], $later->id]
                && $data->every(fn (array $row): bool => $row['date'] >= '2026-11-01'))
            ->where('workEntries.total', 5)
            ->where('workEntries.declinedTotal', 1)
            ->where('workEntries.projects', [['id' => $relaunch->project_id, 'name' => 'Relaunch']])
            ->missing('record'));
});

/*
 * Ada (top)
 * └── Ben ── Cara
 */
it('offers the Work Entries tab to whoever may see the Employee\'s Work Entries, opening the page for them alone, and to nobody else', function (Closure $viewer, bool $offered) {
    $ada = Employee::factory()->top()->create();
    $ben = Employee::factory()->create(['manager_id' => $ada->id]);
    $cara = Employee::factory()->create(['manager_id' => $ben->id]);

    actingAs($viewer($ben, $cara))->get(route('employees.show', ['employee' => $cara, 'tab' => 'work-entries']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab', $offered ? 'work-entries' : 'overview')
            ->where('tabs', fn (Collection $tabs): bool => $tabs->contains('work-entries') === $offered)
            ->when($offered, fn (Assert $page) => $page->has('workEntries'), fn (Assert $page) => $page->missing('workEntries')));
})->with([
    'themselves, viewing their own' => [fn (Employee $ben, Employee $cara) => tap($cara, fn () => giveRole($cara, [Permission::ViewWorkEntries->value => Scope::Own])), true],
    'themselves, managing their own' => [fn (Employee $ben, Employee $cara) => tap($cara, fn () => giveRole($cara, [Permission::ManageWorkEntries->value => Scope::Own])), true],
    'their Manager, viewing their Reports\'' => [fn (Employee $ben) => tap($ben, fn () => giveRole($ben, [Permission::ViewWorkEntries->value => Scope::Reports])), true],
    'someone reopening everyone\'s' => [fn () => employeeWith([Permission::ReopenWorkEntries->value => Scope::Everyone]), true],
    'someone who can only lock' => [fn () => employeeWith([Permission::LockWorkEntries->value => null]), true],
    'their Manager, who approves but has no Work Entry Permissions' => [fn (Employee $ben) => tap($ben, fn () => giveRole($ben, [Permission::ViewEmployeeRecords->value => Scope::Reports])), false],
    'someone who may only see their record' => [fn () => employeeWith([Permission::ViewEmployeeRecords->value => Scope::Everyone]), false],
]);

/*
 * Ada (top)
 * └── Ben ── Cara
 */
it('offers each viewer only what they may do to each Work Entry: the Manager approving and declining Pending ones, reopeners and lockers acting on Approved ones, and nobody on their own but locking', function (Closure $viewer, array $page, array $byState) {
    $ada = Employee::factory()->top()->create();
    $ben = Employee::factory()->create(['manager_id' => $ada->id]);
    $cara = Employee::factory()->create(['manager_id' => $ben->id]);
    $relaunch = assignmentOf($cara, 'Relaunch');
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-02']);
    WorkEntry::factory()->for($relaunch)->approved($ben)->create(['date' => '2026-11-03']);
    WorkEntry::factory()->for($relaunch)->approved($ben)->locked($ada)->create(['date' => '2026-11-04']);
    WorkEntry::factory()->for($relaunch)->declined($ben)->create(['date' => '2026-11-05']);

    actingAs($viewer($ben, $cara))->get(route('employees.show', ['employee' => $cara, 'tab' => 'work-entries']))->assertOk()
        ->assertInertia(fn (Assert $assert) => $assert
            ->where('workEntries.canDecide', $page['decide'])
            ->where('workEntries.canReopen', $page['reopen'])
            ->where('workEntries.canLock', $page['lock'])
            ->where('workEntries.canRecord', $page['record'])
            ->where('workEntries.entries.data', fn (Collection $data): bool => $data
                ->mapWithKeys(fn (array $row): array => [$row['state'] => [$row['can_decide'], $row['can_reopen'], $row['can_lock']]])
                ->all() === $byState));
})->with([
    'their Manager, who may also reopen their Reports\' and lock' => [
        fn (Employee $ben) => tap($ben, fn () => giveRole($ben, [Permission::ViewWorkEntries->value => Scope::Reports, Permission::ReopenWorkEntries->value => Scope::Reports, Permission::LockWorkEntries->value => null])),
        ['decide' => true, 'reopen' => true, 'lock' => true, 'record' => false],
        ['pending' => [true, false, false], 'approved' => [false, true, true], 'locked' => [false, false, false], 'declined' => [false, false, false]],
    ],
    'their Manager, who may only see them' => [
        fn (Employee $ben) => tap($ben, fn () => giveRole($ben, [Permission::ViewWorkEntries->value => Scope::Reports])),
        ['decide' => true, 'reopen' => false, 'lock' => false, 'record' => false],
        ['pending' => [true, false, false], 'approved' => [false, false, false], 'locked' => [false, false, false], 'declined' => [false, false, false]],
    ],
    'themselves, managing their own, reopening everyone\'s and locking' => [
        fn (Employee $ben, Employee $cara) => tap($cara, fn () => giveRole($cara, [Permission::ManageWorkEntries->value => Scope::Own, Permission::ReopenWorkEntries->value => Scope::Everyone, Permission::LockWorkEntries->value => null])),
        ['decide' => false, 'reopen' => false, 'lock' => true, 'record' => true],
        ['pending' => [false, false, false], 'approved' => [false, false, true], 'locked' => [false, false, false], 'declined' => [false, false, false]],
    ],
]);

it('lets the Project and state filters narrow the Employee\'s Work Entries, falling back to any Project when theirs has none in the Period', function () {
    $cara = Employee::factory()->create(['manager_id' => Employee::factory()->top()->create()->id]);
    $relaunch = assignmentOf($cara, 'Relaunch');
    $billing = assignmentOf($cara, 'Billing');
    $pendingOnRelaunch = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-02']);
    WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2026-11-03']);
    WorkEntry::factory()->for($billing)->create(['date' => '2026-11-04']);
    $elsewhere = assignmentOf(Employee::factory()->create(), 'Archive');
    WorkEntry::factory()->for($elsewhere)->create(['date' => '2026-11-04']);
    actingAs(employeeWith([Permission::ViewWorkEntries->value => Scope::Everyone]));

    get(route('employees.show', ['employee' => $cara, 'tab' => 'work-entries', 'project' => $relaunch->project_id, 'state' => 'pending']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('workEntries.filters.project', $relaunch->project_id)
            ->where('workEntries.filters.state', 'pending')
            ->where('workEntries.entries.data', fn (Collection $data): bool => $data->pluck('id')->all() === [$pendingOnRelaunch->id])
            ->where('workEntries.projects', [['id' => $billing->project_id, 'name' => 'Billing'], ['id' => $relaunch->project_id, 'name' => 'Relaunch']]));
    get(route('employees.show', ['employee' => $cara, 'tab' => 'work-entries', 'project' => $elsewhere->project_id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('workEntries.filters.project', null)
            ->where('workEntries.entries.total', 3));
});

it('shows the top Employee, who records no Work Entries, an empty list they can\'t record into', function () {
    $ada = Employee::factory()->top()->create();
    giveRole($ada, [Permission::ManageWorkEntries->value => Scope::Own]);

    actingAs($ada)->get(route('employees.show', ['employee' => $ada, 'tab' => 'work-entries']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('workEntries.entries.total', 0)
            ->where('workEntries.projects', [])
            ->where('workEntries.canRecord', false));
});

it('sends every matching Work Entry\'s ID only when asked for, to select them all', function () {
    $cara = Employee::factory()->create(['manager_id' => Employee::factory()->top()->create()->id]);
    $entries = WorkEntry::factory()->for(assignmentOf($cara))->approved()->count(2)->create(['date' => '2026-11-02']);
    actingAs(employeeWith([Permission::LockWorkEntries->value => null]));
    $url = route('employees.show', ['employee' => $cara, 'tab' => 'work-entries', 'state' => 'approved']);

    get($url)->assertInertia(fn (Assert $page) => $page->missing('workEntries.matchingIds'));
    get($url)->assertInertia(fn (Assert $page) => $page
        ->reloadOnly('workEntries.matchingIds', fn (Assert $reload) => $reload
            ->where('workEntries.matchingIds', $entries->pluck('id')->sort()->values()->all())));
});
