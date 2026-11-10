<?php

use App\Enums\Permission;
use App\Http\Controllers\DirectoryController;
use App\Models\Department;
use App\Models\Employee;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

covers(DirectoryController::class, Employee::class);

/**
 * An Employee who may browse the directory and see anyone's Reporting Chain.
 */
function chainViewer(): Employee
{
    return employeeWith([Permission::ViewDirectory->value => null]);
}

it('shows an Employee\'s Reporting Chain, from them up to the top Employee', function () {
    $top = Employee::factory()->top()->create([
        'name' => 'Ada Top',
        'department_id' => Department::factory()->create(['name' => 'Board'])->id,
    ]);
    $ben = Employee::factory()->create(['name' => 'Ben Manager', 'manager_id' => $top->id]);
    $cara = Employee::factory()->create([
        'name' => 'Cara Report',
        'manager_id' => $ben->id,
        'department_id' => Department::factory()->create(['name' => 'Design'])->id,
    ]);

    actingAs(chainViewer())->get(route('directory.show', $cara))
        ->assertInertia(fn (Assert $page) => $page
            ->component('directory/Show')
            ->has('chain', 3)
            ->where('chain.0', ['id' => $cara->id, 'name' => 'Cara Report', 'department' => 'Design'])
            ->where('chain.1.id', $ben->id)
            ->where('chain.2', ['id' => $top->id, 'name' => 'Ada Top', 'department' => 'Board']),
        );
});

it('shows the top Employee\'s Reporting Chain as just them', function () {
    $top = Employee::factory()->top()->create();

    actingAs(chainViewer())->get(route('directory.show', $top))
        ->assertInertia(fn (Assert $page) => $page
            ->has('chain', 1)
            ->where('chain.0.id', $top->id),
        );
});

it('skips Managers who have left, going straight to the next one up who hasn\'t', function () {
    $top = Employee::factory()->top()->create();
    $gone = Employee::factory()->former()->create(['manager_id' => $top->id]);
    $ben = Employee::factory()->create(['manager_id' => $gone->id]);
    $alsoGone = Employee::factory()->former()->create(['manager_id' => $ben->id]);
    $cara = Employee::factory()->create(['manager_id' => $alsoGone->id]);

    actingAs(chainViewer())->get(route('directory.show', $cara))
        ->assertInertia(fn (Assert $page) => $page
            ->has('chain', 3)
            ->where('chain.0.id', $cara->id)
            ->where('chain.1.id', $ben->id)
            ->where('chain.2.id', $top->id),
        );
});

it('shows a Future Employee\'s Reporting Chain', function () {
    $top = Employee::factory()->top()->create();
    $newcomer = Employee::factory()->future()->create(['manager_id' => $top->id]);

    actingAs(chainViewer())->get(route('directory.show', $newcomer))
        ->assertInertia(fn (Assert $page) => $page->has('chain', 2));
});

it('has no Reporting Chain page for a Former Employee', function () {
    $top = Employee::factory()->top()->create();
    $gone = Employee::factory()->former()->create(['manager_id' => $top->id]);

    actingAs(chainViewer())->get(route('directory.show', $gone))->assertNotFound();
});

it('refuses a Reporting Chain to an Employee without view directory', function () {
    $top = Employee::factory()->top()->create();

    actingAs(employeeWith([Permission::ManageDepartments->value => null]))
        ->get(route('directory.show', $top))
        ->assertForbidden();
});
