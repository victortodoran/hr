<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\Employees\EmployeeController;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Project;
use App\Policies\EmployeePolicy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(EmployeeController::class, EmployeePolicy::class, Employee::class);

it('lists every Employee to someone who may see everyone\'s record, Future and Former ones included, by name', function () {
    travelTo('2026-03-10');
    $finance = Department::factory()->create(['name' => 'Finance']);
    $dana = Employee::factory()->create(['name' => 'Dana Top', 'department_id' => $finance->id, 'start_date' => '2020-01-01']);
    $cara = Employee::factory()->reportingTo($dana)->create(['name' => 'Cara Lane', 'department_id' => $finance->id, 'start_date' => '2026-04-01']);
    $ben = Employee::factory()->reportingTo($dana)->create(['name' => 'Ben Ford', 'department_id' => $finance->id, 'start_date' => '2024-01-01', 'leaving_date' => '2026-02-28']);
    $ana = employeeWith([Permission::ViewEmployeeRecords->value => Scope::Everyone], ['name' => 'Ana Pop', 'department_id' => $finance->id, 'manager_id' => $dana->id, 'start_date' => '2025-01-01']);
    Project::factory()->internal()->create();
    $ana->createInternalAssignment('Accountant');

    actingAs($ana)->get(route('employees.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('employees/Index')
            ->where('employees.data', [
                ['id' => $ana->id, 'name' => 'Ana Pop', 'job_title' => 'Accountant', 'status' => 'current', 'start_date' => '2025-01-01', 'department' => 'Finance', 'manager' => ['id' => $dana->id, 'name' => 'Dana Top']],
                ['id' => $ben->id, 'name' => 'Ben Ford', 'job_title' => null, 'status' => 'former', 'start_date' => '2024-01-01', 'department' => 'Finance', 'manager' => ['id' => $dana->id, 'name' => 'Dana Top']],
                ['id' => $cara->id, 'name' => 'Cara Lane', 'job_title' => null, 'status' => 'future', 'start_date' => '2026-04-01', 'department' => 'Finance', 'manager' => ['id' => $dana->id, 'name' => 'Dana Top']],
                ['id' => $dana->id, 'name' => 'Dana Top', 'job_title' => null, 'status' => 'current', 'start_date' => '2020-01-01', 'department' => 'Finance', 'manager' => null],
            ])
            ->where('search', '')
            ->where('status', null)
            ->where('department', null)
            ->where('manager', null)
            ->where('counts', ['all' => 4, 'future' => 1, 'current' => 2, 'former' => 1])
            ->where('departments', [['id' => $finance->id, 'name' => 'Finance']])
            ->where('managers', [['id' => $dana->id, 'name' => 'Dana Top']])
            ->where('canAdd', false),
        );
});

/*
 * Dana (top)
 * ├── Ben ── Cara ── Dev
 * └── Eve
 */
it('lists only the Employees the viewer\'s Scopes cover, each from its own Role', function (array $scopes, array $listed) {
    $dana = Employee::factory()->create(['name' => 'Dana']);
    $ben = employeeWith([Permission::ViewEmployeeRecords->value => array_shift($scopes)], ['name' => 'Ben', 'manager_id' => $dana->id]);
    foreach ($scopes as $scope) {
        giveRole($ben, [Permission::ViewEmployeeRecords->value => $scope]);
    }
    $cara = Employee::factory()->reportingTo($ben)->create(['name' => 'Cara']);
    Employee::factory()->former()->reportingTo($cara)->create(['name' => 'Dev']);
    Employee::factory()->reportingTo($dana)->create(['name' => 'Eve']);

    actingAs($ben)->get(route('employees.index'))
        ->assertInertia(fn (Assert $page) => $page->where('employees.data', fn (Collection $rows): bool => $rows->pluck('name')->all() === $listed));
})->with([
    'own' => [[Scope::Own], ['Ben']],
    'reports, indirect and Former ones included' => [[Scope::Reports], ['Cara', 'Dev']],
    'own and reports combined' => [[Scope::Own, Scope::Reports], ['Ben', 'Cara', 'Dev']],
    'everyone' => [[Scope::Everyone], ['Ben', 'Cara', 'Dana', 'Dev', 'Eve']],
]);

it('narrows the list to one status', function (string $status, array $listed) {
    travelTo('2026-03-10');
    Employee::factory()->create(['name' => 'Future', 'start_date' => '2026-03-11']);
    Employee::factory()->create(['name' => 'Starts today', 'start_date' => '2026-03-10']);
    Employee::factory()->create(['name' => 'Leaves today', 'start_date' => '2025-01-01', 'leaving_date' => '2026-03-10']);
    Employee::factory()->create(['name' => 'Former', 'start_date' => '2025-01-01', 'leaving_date' => '2026-03-09']);
    $viewer = employeeWith([Permission::ViewEmployeeRecords->value => Scope::Everyone], ['name' => 'Viewer', 'start_date' => '2025-01-01']);

    actingAs($viewer)->get(route('employees.index', ['status' => $status]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('status', $status)
            ->where('employees.data', fn (Collection $rows): bool => $rows->pluck('name')->all() === $listed));
})->with([
    'future' => ['future', ['Future']],
    'current' => ['current', ['Leaves today', 'Starts today', 'Viewer']],
    'former' => ['former', ['Former']],
]);

it('searches the list by part of a name, treating wildcards literally', function () {
    Employee::factory()->create(['name' => 'Cara Lane']);
    Employee::factory()->create(['name' => 'Ben Ford']);
    Employee::factory()->create(['name' => '100% Sure']);
    $viewer = employeeWith([Permission::ViewEmployeeRecords->value => Scope::Everyone], ['name' => 'Viewer']);

    actingAs($viewer)->get(route('employees.index', ['search' => 'lan']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('search', 'lan')
            ->where('employees.data', fn (Collection $rows): bool => $rows->pluck('name')->all() === ['Cara Lane']));

    actingAs($viewer)->get(route('employees.index', ['search' => '%']))
        ->assertInertia(fn (Assert $page) => $page->where('employees.data', fn (Collection $rows): bool => $rows->pluck('name')->all() === ['100% Sure']));
});

it('narrows the list to one Department and one effective Manager, keeping the other filters', function () {
    $finance = Department::factory()->create(['name' => 'Finance']);
    $sales = Department::factory()->create(['name' => 'Sales']);
    $dana = Employee::factory()->create(['name' => 'Dana Top', 'department_id' => $sales->id]);
    $gone = Employee::factory()->former()->reportingTo($dana)->create(['name' => 'Gone Manager', 'department_id' => $sales->id]);
    Employee::factory()->reportingTo($gone)->create(['name' => 'Cara Lane', 'department_id' => $finance->id]);
    Employee::factory()->reportingTo($dana)->create(['name' => 'Ben Ford', 'department_id' => $finance->id]);
    Employee::factory()->reportingTo($dana)->create(['name' => 'Eve Stan', 'department_id' => $sales->id]);
    $viewer = employeeWith([Permission::ViewEmployeeRecords->value => Scope::Everyone], ['name' => 'Viewer', 'department_id' => $sales->id]);

    actingAs($viewer)->get(route('employees.index', ['department' => $finance->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('department', $finance->id)
            ->where('employees.data', fn (Collection $rows): bool => $rows->pluck('name')->all() === ['Ben Ford', 'Cara Lane']));

    actingAs($viewer)->get(route('employees.index', ['manager' => $dana->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('manager', $dana->id)
            ->where('employees.data', fn (Collection $rows): bool => $rows->pluck('name')->all() === ['Ben Ford', 'Cara Lane', 'Eve Stan', 'Gone Manager']));

    actingAs($viewer)->get(route('employees.index', ['department' => $finance->id, 'manager' => $dana->id, 'search' => 'ben']))
        ->assertInertia(fn (Assert $page) => $page->where('employees.data', fn (Collection $rows): bool => $rows->pluck('name')->all() === ['Ben Ford']));
});

it('counts each status among the Employees the search, Department and Manager match, whatever the status chosen', function () {
    travelTo('2026-03-10');
    $finance = Department::factory()->create();
    $sales = Department::factory()->create();
    $dana = Employee::factory()->create(['department_id' => $sales->id, 'start_date' => '2020-01-01']);
    Employee::factory()->reportingTo($dana)->create(['name' => 'Ann Future', 'department_id' => $finance->id, 'start_date' => '2026-04-01']);
    Employee::factory()->reportingTo($dana)->count(2)->create(['name' => 'Ann Current', 'department_id' => $finance->id, 'start_date' => '2025-01-01']);
    Employee::factory()->reportingTo($dana)->create(['name' => 'Ann Former', 'department_id' => $finance->id, 'start_date' => '2025-01-01', 'leaving_date' => '2026-01-31']);
    Employee::factory()->reportingTo($dana)->create(['name' => 'Ann Elsewhere', 'department_id' => $sales->id, 'start_date' => '2025-01-01']);
    Employee::factory()->create(['name' => 'Ann Unmanaged', 'department_id' => $finance->id, 'start_date' => '2025-01-01']);
    Employee::factory()->reportingTo($dana)->create(['name' => 'Ben Current', 'department_id' => $finance->id, 'start_date' => '2025-01-01']);
    $viewer = employeeWith([Permission::ViewEmployeeRecords->value => Scope::Everyone], ['department_id' => $sales->id, 'start_date' => '2025-01-01']);

    actingAs($viewer)->get(route('employees.index', ['search' => 'ann', 'department' => $finance->id, 'manager' => $dana->id, 'status' => 'former']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('employees.data', 1)
            ->where('counts', ['all' => 4, 'future' => 1, 'current' => 2, 'former' => 1]));
});

/*
 * Dana (top, Sales)
 * ├── Ben (Finance) ── Cara (Finance) ── Dev (Engineering)
 * └── Eve (Sales)
 */
it('counts and offers to filter by only what a viewer with limited Scope may see', function () {
    $finance = Department::factory()->create(['name' => 'Finance']);
    $engineering = Department::factory()->create(['name' => 'Engineering']);
    $sales = Department::factory()->create(['name' => 'Sales']);
    $dana = Employee::factory()->create(['name' => 'Dana', 'department_id' => $sales->id]);
    $ben = employeeWith([Permission::ViewEmployeeRecords->value => Scope::Reports], ['name' => 'Ben', 'manager_id' => $dana->id, 'department_id' => $finance->id]);
    $cara = Employee::factory()->reportingTo($ben)->create(['name' => 'Cara', 'department_id' => $finance->id]);
    Employee::factory()->former()->reportingTo($cara)->create(['name' => 'Dev', 'department_id' => $engineering->id]);
    Employee::factory()->reportingTo($dana)->create(['name' => 'Eve', 'department_id' => $sales->id]);

    actingAs($ben)->get(route('employees.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('counts', ['all' => 2, 'future' => 0, 'current' => 1, 'former' => 1])
            ->where('departments', [['id' => $engineering->id, 'name' => 'Engineering'], ['id' => $finance->id, 'name' => 'Finance']])
            ->where('managers', [['id' => $ben->id, 'name' => 'Ben'], ['id' => $cara->id, 'name' => 'Cara']]));

    actingAs($ben)->get(route('employees.index', ['department' => $sales->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('employees.data', 0)
            ->where('counts.all', 0));

    actingAs($ben)->get(route('employees.index', ['manager' => $dana->id]))
        ->assertInertia(fn (Assert $page) => $page->has('employees.data', 0));
});

it('refuses a Department or Manager that isn\'t an ID', function (string $filter) {
    actingAs(employeeWith([Permission::ViewEmployeeRecords->value => Scope::Everyone]))
        ->get(route('employees.index', [$filter => 'finance']))
        ->assertSessionHasErrors($filter);
})->with(['department', 'manager']);

it('refuses a status that isn\'t one', function () {
    actingAs(employeeWith([Permission::ViewEmployeeRecords->value => Scope::Everyone]))
        ->get(route('employees.index', ['status' => 'retired']))
        ->assertSessionHasErrors('status');
});

it('offers Add Employee only to those who add Employees', function () {
    actingAs(employeeWith([Permission::ViewEmployeeRecords->value => Scope::Everyone, Permission::AddEmployees->value => null]))
        ->get(route('employees.index'))
        ->assertInertia(fn (Assert $page) => $page->where('canAdd', true));
});

it('refuses the list to someone who may see no Employee\'s record', function () {
    actingAs(employeeWith([Permission::ViewDirectory->value => null, Permission::ViewAssignments->value => Scope::Everyone]))
        ->get(route('employees.index'))
        ->assertForbidden();
});

it('rejects a search that is not text or is too long', function (mixed $search, string $message) {
    actingAs(employeeWith([Permission::ViewEmployeeRecords->value => Scope::Everyone]))
        ->get(route('employees.index', ['search' => $search]))
        ->assertSessionHasErrors(['search' => $message]);
})->with([
    'not text' => [['Ann'], 'The search field must be a string.'],
    'too long' => [str_repeat('a', 256), 'The search field must not be greater than 255 characters.'],
]);

it('shows everyone for an empty search and no status', function () {
    $viewer = employeeWith([Permission::ViewEmployeeRecords->value => Scope::Everyone]);
    Employee::factory()->former()->create();

    actingAs($viewer)->get(route('employees.index', ['search' => '', 'status' => '', 'department' => '', 'manager' => '']))
        ->assertInertia(fn (Assert $page) => $page->has('employees.data', 2)->where('status', null)->where('department', null)->where('manager', null));
});

it('loads the list in the same number of queries however many Employees it lists', function () {
    $viewer = employeeWith([Permission::ViewEmployeeRecords->value => Scope::Everyone]);
    $top = Employee::factory()->create();
    $queriesToList = function () use ($viewer): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        actingAs(Employee::query()->findOrFail($viewer->id))->get(route('employees.index'))->assertOk();

        return count(DB::getQueryLog());
    };

    Employee::factory()->reportingTo($top)->create();
    $forThree = $queriesToList();

    Employee::factory()->count(5)->reportingTo($top)->create();

    expect($queriesToList())->toBe($forThree);
});

it('pages the list 25 Employees at a time, keeping the filters in the links', function () {
    $viewer = employeeWith([Permission::ViewEmployeeRecords->value => Scope::Everyone], ['name' => 'Aaron Viewer']);
    Employee::factory()->count(25)->sequence(fn ($sequence) => ['name' => sprintf('Anna %02d', $sequence->index)])->create();
    Employee::factory()->create(['name' => 'Ben Not Matching']);

    actingAs($viewer)->get(route('employees.index', ['search' => 'a', 'status' => 'current', 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('employees.data', 2)
            ->where('employees.data.0.name', 'Anna 24')
            ->where('employees.data.1.name', 'Ben Not Matching')
            ->etc(),
        );

    actingAs($viewer)->get(route('employees.index', ['search' => 'a', 'status' => 'current']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('employees.data', 25)
            ->where('employees.next_page_url', route('employees.index', ['search' => 'a', 'status' => 'current', 'page' => 2]))
            ->etc(),
        );
});
