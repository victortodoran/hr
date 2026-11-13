<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\WorkEntryListController;
use App\Http\Requests\WorkEntryFiltersRequest;
use App\Http\Requests\WorkEntryListRequest;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\WorkEntry;
use App\Policies\WorkEntryPolicy;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(WorkEntryListController::class, WorkEntryPolicy::class, Employee::class, WorkEntry::class, WorkEntryFiltersRequest::class, WorkEntryListRequest::class);

beforeEach(function () {
    travelTo('2026-11-11 09:00');
});

it('opens on the current month and the first Employee by name with Work Entries in it, read-only, for someone who may view everyone\'s', function () {
    $ada = Employee::factory()->top()->create(['name' => 'Ada']);
    $dan = employeeWith([Permission::ViewWorkEntries->value => Scope::Everyone], ['name' => 'Dan', 'manager_id' => $ada->id]);
    $cara = Employee::factory()->create(['name' => 'Cara', 'manager_id' => $ada->id]);
    $bea = Employee::factory()->create(['name' => 'Bea', 'manager_id' => $ada->id]);
    $carasRelaunch = assignmentOf($cara, 'Relaunch', ['start_date' => '2026-10-01']);
    $carasBilling = assignmentOf($cara, 'Billing');
    $locked = WorkEntry::factory()->for($carasBilling)->approved($dan)->locked($dan)->create(['date' => '2026-11-02']);
    $approved = WorkEntry::factory()->for($carasRelaunch)->approved($dan)->create(['date' => '2026-11-09']);
    $pending = WorkEntry::factory()->for($carasRelaunch)->create(['date' => '2026-11-10']);
    WorkEntry::factory()->for($carasRelaunch)->create(['date' => '2026-10-30']);
    WorkEntry::factory()->for(assignmentOf($dan, 'Archive'))->create(['date' => '2026-11-10']);
    WorkEntry::factory()->for(assignmentOf($bea, 'Intranet', ['start_date' => '2026-10-01']))->create(['date' => '2026-10-15']);

    $response = actingAs($dan)->get(route('work-entry-list.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('work-entries/List', false)
        ->where('filters', ['month' => '2026-11', 'employee' => $cara->id, 'project' => null, 'state' => null])
        ->where('canReopen', false)
        ->where('canLock', false)
        ->where('entries.data', fn (Collection $data): bool => $data->map(fn (array $row): array => [$row['id'], $row['state'], $row['can_reopen'], $row['can_lock']])->all() === [
            [$locked->id, 'locked', false, false],
            [$approved->id, 'approved', false, false],
            [$pending->id, 'pending', false, false],
        ])
        ->where('employees', [['id' => $cara->id, 'name' => 'Cara', 'count' => 3], ['id' => $dan->id, 'name' => 'Dan', 'count' => 1]])
        ->where('projects', [['id' => $carasBilling->project_id, 'name' => 'Billing'], ['id' => $carasRelaunch->project_id, 'name' => 'Relaunch']]));
});

it('shows an earlier month, the current one in place of a later one, and refuses one it can\'t read', function () {
    $dan = employeeWith([Permission::ViewWorkEntries->value => Scope::Everyone]);
    $relaunch = assignmentOf(Employee::factory()->create(), 'Relaunch', ['start_date' => '2026-10-01']);
    $october = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-10-31']);
    $november = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-01']);

    actingAs($dan)->get(route('work-entry-list.index', ['month' => '2026-10']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.month', '2026-10')
            ->where('entries.data', fn (Collection $data): bool => $data->pluck('id')->all() === [$october->id]));
    actingAs($dan)->get(route('work-entry-list.index', ['month' => '2026-12']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.month', '2026-11')
            ->where('entries.data', fn (Collection $data): bool => $data->pluck('id')->all() === [$november->id]));
    actingAs($dan)->get(route('work-entry-list.index', ['month' => 'November']))
        ->assertInvalid(['month' => 'The month field must match the format Y-m.']);
});

it('lists only a Manager\'s Reports for someone who may view their Reports\' Work Entries', function () {
    $ada = Employee::factory()->top()->create(['name' => 'Ada']);
    $ben = employeeWith([Permission::ViewWorkEntries->value => Scope::Reports], ['name' => 'Ben', 'manager_id' => $ada->id]);
    $cara = Employee::factory()->create(['name' => 'Cara', 'manager_id' => $ben->id]);
    $dev = Employee::factory()->create(['name' => 'Dev', 'manager_id' => $cara->id]);
    $caras = WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-09']);
    $devs = WorkEntry::factory()->for(assignmentOf($dev))->approved()->create(['date' => '2026-11-10']);
    WorkEntry::factory()->for(assignmentOf($ben))->create(['date' => '2026-11-10']);
    $outsider = Employee::factory()->create(['name' => 'Aaron', 'manager_id' => $ada->id]);
    WorkEntry::factory()->for(assignmentOf($outsider))->create(['date' => '2026-11-10']);

    actingAs($ben)->get(route('work-entry-list.index', ['employee' => 'all']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('entries.data', fn (Collection $data): bool => $data->pluck('id')->all() === [$caras->id, $devs->id])
            ->where('employees', [['id' => $cara->id, 'name' => 'Cara', 'count' => 1], ['id' => $dev->id, 'name' => 'Dev', 'count' => 1]]));
    actingAs($ben)->get(route('work-entry-list.index', ['employee' => $outsider->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.employee', $cara->id)
            ->where('entries.data', fn (Collection $data): bool => $data->pluck('id')->all() === [$caras->id]));
});

it('lists everyone\'s Work Entries on a Project in a month for any Employee, offering every Project with Work Entries that month', function () {
    $dan = employeeWith([Permission::ViewWorkEntries->value => Scope::Everyone], ['name' => 'Dan']);
    $cara = Employee::factory()->create(['name' => 'Cara']);
    $dev = Employee::factory()->create(['name' => 'Dev']);
    $carasRelaunch = assignmentOf($cara, 'Relaunch', ['start_date' => '2026-10-01']);
    $devsRelaunch = Assignment::factory()->for($dev)->for($carasRelaunch->project)->create(['start_date' => '2026-10-01', 'end_date' => null]);
    $devsBilling = assignmentOf($dev, 'Billing', ['start_date' => '2026-10-01']);
    assignmentOf($cara, 'Archive', ['start_date' => '2026-10-01']);
    $caras = WorkEntry::factory()->for($carasRelaunch)->create(['date' => '2026-10-06']);
    $devs = WorkEntry::factory()->for($devsRelaunch)->approved()->create(['date' => '2026-10-07', 'minutes' => 90]);
    WorkEntry::factory()->for($devsBilling)->create(['date' => '2026-10-07']);
    WorkEntry::factory()->for($devsRelaunch)->create(['date' => '2026-11-02']);

    actingAs($dan)->get(route('work-entry-list.index', ['month' => '2026-10', 'employee' => 'all', 'project' => $carasRelaunch->project_id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters', ['month' => '2026-10', 'employee' => 'all', 'project' => $carasRelaunch->project_id, 'state' => null])
            ->where('entries.data', fn (Collection $data): bool => $data->pluck('id')->all() === [$caras->id, $devs->id])
            ->where('employees', [['id' => $cara->id, 'name' => 'Cara', 'count' => 1], ['id' => $dev->id, 'name' => 'Dev', 'count' => 2]])
            ->where('projects', [['id' => $devsBilling->project_id, 'name' => 'Billing'], ['id' => $carasRelaunch->project_id, 'name' => 'Relaunch']]));
});

it('falls back to any Project when the Employee shown has no Work Entries on the one asked for that month', function () {
    $dan = employeeWith([Permission::ViewWorkEntries->value => Scope::Everyone]);
    $cara = Employee::factory()->create(['name' => 'Cara']);
    $relaunch = assignmentOf($cara);
    $billing = assignmentOf(Employee::factory()->create(['name' => 'Dev']), 'Billing');
    $caras = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10']);
    WorkEntry::factory()->for($billing)->create(['date' => '2026-11-10']);

    actingAs($dan)->get(route('work-entry-list.index', ['employee' => $cara->id, 'project' => $billing->project_id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters', ['month' => '2026-11', 'employee' => $cara->id, 'project' => null, 'state' => null])
            ->where('projects', [['id' => $relaunch->project_id, 'name' => 'Relaunch']])
            ->where('entries.data', fn (Collection $data): bool => $data->pluck('id')->all() === [$caras->id]));
});

it('shows only the chosen state, offering only Employees with Work Entries in it, and refuses one it doesn\'t know', function () {
    $dan = employeeWith([Permission::ViewWorkEntries->value => Scope::Everyone]);
    $relaunch = assignmentOf(Employee::factory()->create(['name' => 'Cara']));
    $devs = assignmentOf(Employee::factory()->create(['name' => 'Dev']));
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10']);
    $approved = WorkEntry::factory()->for($devs)->approved()->create(['date' => '2026-11-10', 'minutes' => 90]);

    actingAs($dan)->get(route('work-entry-list.index', ['employee' => $relaunch->employee_id, 'state' => 'approved']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.state', 'approved')
            ->where('filters.employee', $devs->employee_id)
            ->where('employees', [['id' => $devs->employee_id, 'name' => 'Dev', 'count' => 1]])
            ->where('entries.data', fn (Collection $data): bool => $data->pluck('id')->all() === [$approved->id])
            ->where('total', 1.5));
    actingAs($dan)->get(route('work-entry-list.index', ['state' => 'invoiced']))
        ->assertInvalid(['state' => 'The selected state is invalid.']);
});

it('offers nobody to choose in a month without Work Entries, keeping any Employee when asked for', function () {
    $dan = employeeWith([Permission::ViewWorkEntries->value => Scope::Everyone]);
    WorkEntry::factory()->for(assignmentOf(Employee::factory()->create()))->create(['date' => '2026-11-10']);

    actingAs($dan)->get(route('work-entry-list.index', ['month' => '2026-09']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters', ['month' => '2026-09', 'employee' => null, 'project' => null, 'state' => null])
            ->where('employees', [])
            ->where('projects', [])
            ->where('entries.data', []));
    actingAs($dan)->get(route('work-entry-list.index', ['month' => '2026-09', 'employee' => 'all']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.employee', 'all')
            ->where('entries.data', []));
    actingAs($dan)->get(route('work-entry-list.index', ['employee' => 'anyone']))
        ->assertInvalid(['employee']);
});

it('gives every matching Work Entry\'s ID when asked, for selecting them all', function () {
    $dan = employeeWith([Permission::ViewWorkEntries->value => Scope::Everyone]);
    $relaunch = assignmentOf(Employee::factory()->create());
    $ids = WorkEntry::factory()->for($relaunch)->approved()->count(26)->create(['date' => '2026-11-10'])->pluck('id')->all();
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10']);

    actingAs($dan)->get(route('work-entry-list.index', ['state' => 'approved']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('entries.data', 25)
            ->missing('matchingIds')
            ->reloadOnly('matchingIds', fn (Assert $reload) => $reload->where('matchingIds', $ids)));
});

it('refuses the list, and leaves it out of the navigation, for someone who may only see their own Work Entries', function () {
    $cara = employeeWith([Permission::ViewWorkEntries->value => Scope::Own, Permission::ManageWorkEntries->value => Scope::Own]);
    $dan = employeeWith([Permission::ViewWorkEntries->value => Scope::Everyone]);

    actingAs($cara)->get(route('work-entry-list.index'))->assertForbidden();
    actingAs($cara)->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canSeeWorkEntryList', false));
    actingAs($dan)->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canSeeWorkEntryList', true));
});
