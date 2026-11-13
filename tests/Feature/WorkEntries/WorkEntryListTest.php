<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\WorkEntryListController;
use App\Http\Requests\WorkEntryFiltersRequest;
use App\Http\Requests\WorkEntryListRequest;
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

it('lists everyone\'s Work Entries in every state, read-only, for someone who may view everyone\'s', function () {
    $ada = Employee::factory()->top()->create(['name' => 'Ada']);
    $dan = employeeWith([Permission::ViewWorkEntries->value => Scope::Everyone], ['name' => 'Dan', 'manager_id' => $ada->id]);
    $cara = Employee::factory()->create(['name' => 'Cara', 'manager_id' => $ada->id]);
    $pending = WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-10']);
    $approved = WorkEntry::factory()->for(assignmentOf($ada))->approved($dan)->create(['date' => '2026-11-09']);
    $locked = WorkEntry::factory()->for(assignmentOf($cara, 'Billing'))->approved($dan)->locked($dan)->create(['date' => '2026-11-02']);

    $response = actingAs($dan)->get(route('work-entry-list.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('work-entries/List', false)
        ->where('filters', ['employee' => null, 'project' => null, 'from' => null, 'to' => null, 'state' => null])
        ->where('canReopen', false)
        ->where('canLock', false)
        ->where('entries.data', fn (Collection $data): bool => $data->map(fn (array $row): array => [$row['id'], $row['state'], $row['can_reopen'], $row['can_lock']])->all() === [
            [$locked->id, 'locked', false, false],
            [$approved->id, 'approved', false, false],
            [$pending->id, 'pending', false, false],
        ])
        ->where('employees', [['id' => $ada->id, 'name' => 'Ada'], ['id' => $cara->id, 'name' => 'Cara'], ['id' => $dan->id, 'name' => 'Dan']]));
});

it('lists only a Manager\'s Reports for someone who may view their Reports\' Work Entries', function () {
    $ada = Employee::factory()->top()->create(['name' => 'Ada']);
    $ben = employeeWith([Permission::ViewWorkEntries->value => Scope::Reports], ['name' => 'Ben', 'manager_id' => $ada->id]);
    $cara = Employee::factory()->create(['name' => 'Cara', 'manager_id' => $ben->id]);
    $dev = Employee::factory()->create(['name' => 'Dev', 'manager_id' => $cara->id]);
    $caras = WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-09']);
    $devs = WorkEntry::factory()->for(assignmentOf($dev))->approved()->create(['date' => '2026-11-10']);
    WorkEntry::factory()->for(assignmentOf($ben))->create(['date' => '2026-11-10']);
    WorkEntry::factory()->for(assignmentOf(Employee::factory()->create(['manager_id' => $ada->id])))->create(['date' => '2026-11-10']);

    actingAs($ben)->get(route('work-entry-list.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('entries.data', fn (Collection $data): bool => $data->pluck('id')->all() === [$caras->id, $devs->id])
            ->where('employees', [['id' => $cara->id, 'name' => 'Cara'], ['id' => $dev->id, 'name' => 'Dev']]));
});

it('shows only the chosen state, and refuses one it doesn\'t know', function () {
    $dan = employeeWith([Permission::ViewWorkEntries->value => Scope::Everyone]);
    $relaunch = assignmentOf(Employee::factory()->create());
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10']);
    $approved = WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2026-11-10', 'minutes' => 90]);

    actingAs($dan)->get(route('work-entry-list.index', ['state' => 'approved']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.state', 'approved')
            ->where('entries.data', fn (Collection $data): bool => $data->pluck('id')->all() === [$approved->id])
            ->where('total', 1.5));
    actingAs($dan)->get(route('work-entry-list.index', ['state' => 'invoiced']))
        ->assertInvalid(['state' => 'The selected state is invalid.']);
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
