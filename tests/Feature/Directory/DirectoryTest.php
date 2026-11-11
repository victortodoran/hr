<?php

use App\Enums\Permission;
use App\Http\Controllers\DirectoryController;
use App\Models\Assignment;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

covers(DirectoryController::class, Employee::class, Assignment::class);

/**
 * An Employee who may browse the directory, like everyone with the starter
 * Employee Role.
 *
 * @param  array<string, mixed>  $attributes
 */
function colleague(array $attributes = []): Employee
{
    return employeeWith([Permission::ViewDirectory->value => null], ['name' => 'Zoe Viewer', ...$attributes]);
}

it('lists current Employees by name with only their name, email, internal Job Title, Department and Manager', function () {
    $viewer = colleague();
    $ben = Employee::factory()->create([
        'name' => 'Ben Manager',
        'email' => 'ben@acme.test',
        'department_id' => Department::factory()->create(['name' => 'Engineering'])->id,
    ]);
    $cara = Employee::factory()->create([
        'name' => 'Cara Report',
        'email' => 'cara@acme.test',
        'department_id' => Department::factory()->create(['name' => 'Design'])->id,
        'manager_id' => $ben->id,
    ]);
    Assignment::factory()->for($cara)->create(['job_title' => 'Client Lead']);
    Assignment::factory()->internal()->for($ben)->create(['job_title' => null]);
    Assignment::factory()->internal()->for($cara)->create(['job_title' => 'Designer', 'capacity' => 40]);

    actingAs($viewer)->get(route('directory.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('directory/Index')
            ->has('employees.data', 3)
            ->has('employees.data.0', fn (Assert $entry) => $entry
                ->where('id', $ben->id)
                ->where('name', 'Ben Manager')
                ->where('email', 'ben@acme.test')
                ->where('job_title', null)
                ->where('department', 'Engineering')
                ->where('manager', null),
            )
            ->has('employees.data.1', fn (Assert $entry) => $entry
                ->where('id', $cara->id)
                ->where('name', 'Cara Report')
                ->where('email', 'cara@acme.test')
                ->where('job_title', 'Designer')
                ->where('department', 'Design')
                ->where('manager', ['id' => $ben->id, 'name' => 'Ben Manager']),
            )
            ->where('employees.data.2.name', 'Zoe Viewer')
            ->etc(),
        );
});

it('leaves out Future and Former Employees', function () {
    $viewer = colleague();
    Employee::factory()->future()->create();
    Employee::factory()->former()->create();

    actingAs($viewer)->get(route('directory.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('employees.data', 1)
            ->where('employees.data.0.id', $viewer->id)
            ->etc(),
        );
});

it('counts the Leaving Date and the Start Date as days in the directory', function () {
    $viewer = colleague();
    $leavingToday = Employee::factory()->create(['name' => 'Ada Leaving', 'start_date' => today()->subYear(), 'leaving_date' => today()]);
    $startingToday = Employee::factory()->create(['name' => 'Bob Starting', 'start_date' => today()]);

    actingAs($viewer)->get(route('directory.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('employees.data.0.id', $leavingToday->id)
            ->where('employees.data.1.id', $startingToday->id)
            ->has('employees.data', 3)
            ->etc(),
        );
});

it('searches the directory by any part of a name, ignoring case', function () {
    $viewer = colleague();
    $cara = Employee::factory()->create(['name' => 'Cara McDonald']);
    Employee::factory()->create(['name' => 'Ben Smith']);

    actingAs($viewer)->get(route('directory.index', ['search' => 'mcdon']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('search', 'mcdon')
            ->has('employees.data', 1)
            ->where('employees.data.0.id', $cara->id)
            ->etc(),
        );
});

it('treats % and _ in a search as ordinary characters', function () {
    $viewer = colleague();
    $odd = Employee::factory()->create(['name' => 'Ann 100% Real']);
    Employee::factory()->create(['name' => 'Ann 1000 Real']);

    actingAs($viewer)->get(route('directory.index', ['search' => '0%']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('employees.data', 1)
            ->where('employees.data.0.id', $odd->id)
            ->etc(),
        );

    actingAs($viewer)->get(route('directory.index', ['search' => 'Ann_1']))
        ->assertInertia(fn (Assert $page) => $page->has('employees.data', 0)->etc());
});

it('shows everyone for an empty search', function () {
    $viewer = colleague();
    Employee::factory()->create();

    actingAs($viewer)->get(route('directory.index', ['search' => '']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('search', '')
            ->has('employees.data', 2)
            ->etc(),
        );
});

it('rejects a search that is not text or is too long', function (mixed $search, string $message) {
    actingAs(colleague())
        ->get(route('directory.index', ['search' => $search]))
        ->assertSessionHasErrors(['search' => $message]);
})->with([
    'not text' => [['Ann'], 'The search field must be a string.'],
    'too long' => [str_repeat('a', 256), 'The search field must not be greater than 255 characters.'],
]);

it('loads the directory in the same number of queries however many Employees it lists', function () {
    $viewer = colleague();
    $top = Employee::factory()->top()->create();
    $queriesToList = function () use ($viewer): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        actingAs($viewer)->get(route('directory.index'))->assertOk();

        return count(DB::getQueryLog());
    };

    Employee::factory()->create(['manager_id' => $top->id]);
    $forThree = $queriesToList();

    Employee::factory()->count(5)->create(['manager_id' => $top->id]);

    expect($queriesToList())->toBe($forThree);
});

it('shows the effective Manager when an Employee\'s own Manager has left', function () {
    $viewer = colleague();
    $top = Employee::factory()->top()->create(['name' => 'Ada Top']);
    $gone = Employee::factory()->former()->create(['name' => 'Gus Gone', 'manager_id' => $top->id]);
    $alsoGone = Employee::factory()->former()->create(['name' => 'Gil Gone', 'manager_id' => $gone->id]);
    $cara = Employee::factory()->create(['name' => 'Cara Report', 'manager_id' => $alsoGone->id]);

    actingAs($viewer)->get(route('directory.index', ['search' => 'Cara']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('employees.data.0.id', $cara->id)
            ->where('employees.data.0.manager', ['id' => $top->id, 'name' => 'Ada Top'])
            ->etc(),
        );
});

it('keeps a Manager who leaves today, and one who has yet to start', function () {
    $viewer = colleague();
    $leavingToday = Employee::factory()->create(['name' => 'Lou Leaving', 'start_date' => today()->subYear(), 'leaving_date' => today()]);
    $starting = Employee::factory()->future()->create(['name' => 'Fay Future']);
    Employee::factory()->create(['name' => 'Bea Report', 'manager_id' => $leavingToday->id]);
    Employee::factory()->create(['name' => 'Bo Report', 'manager_id' => $starting->id]);

    actingAs($viewer)->get(route('directory.index', ['search' => 'Report']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('employees.data.0.manager.name', 'Lou Leaving')
            ->where('employees.data.1.manager.name', 'Fay Future')
            ->etc(),
        );
});

it('pages the directory 25 Employees at a time, keeping the search in the links', function () {
    $viewer = colleague(['name' => 'Aaron Viewer']);
    Employee::factory()->count(25)->sequence(fn ($sequence) => ['name' => sprintf('Anna %02d', $sequence->index)])->create();
    Employee::factory()->create(['name' => 'Ben Not Matching']);

    actingAs($viewer)->get(route('directory.index', ['search' => 'a', 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('employees.data', 2)
            ->where('employees.data.0.name', 'Anna 24')
            ->where('employees.data.1.name', 'Ben Not Matching')
            ->etc(),
        );

    actingAs($viewer)->get(route('directory.index', ['search' => 'a']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('employees.data', 25)
            ->where('employees.next_page_url', route('directory.index', ['search' => 'a', 'page' => 2]))
            ->etc(),
        );
});

it('refuses the directory to an Employee without view directory', function () {
    actingAs(employeeWith([Permission::ManageDepartments->value => null]))
        ->get(route('directory.index'))
        ->assertForbidden();
});
