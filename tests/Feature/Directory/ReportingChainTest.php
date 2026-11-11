<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\DirectoryController;
use App\Models\Assignment;
use App\Models\Department;
use App\Models\Employee;
use App\Support\ReportingChain;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(DirectoryController::class, ReportingChain::class, Employee::class);

beforeEach(fn () => travelTo('2026-03-01 09:00'));

/**
 * An Employee who may browse the directory and see anyone's Reporting Chain.
 */
function chainViewer(): Employee
{
    return employeeWith([Permission::ViewDirectory->value => null]);
}

it('shows an Employee\'s Reporting Chain: everyone above from the top, them, and those they are the Manager of', function () {
    $top = Employee::factory()->top()->create(['name' => 'Ada Top', 'department_id' => Department::factory()->create(['name' => 'Board'])->id]);
    Assignment::factory()->for($top)->internal()->create(['job_title' => 'CEO']);
    $ben = Employee::factory()->create(['name' => 'Ben Manager', 'manager_id' => $top->id, 'department_id' => Department::factory()->create(['name' => 'Product'])->id]);
    $cara = Employee::factory()->create(['name' => 'Cara Report', 'manager_id' => $ben->id, 'department_id' => Department::factory()->create(['name' => 'Design'])->id]);
    $zoe = Employee::factory()->create(['name' => 'Zoe Below', 'manager_id' => $cara->id]);
    $dev = Employee::factory()->create(['name' => 'Dev Below', 'manager_id' => $cara->id]);
    Employee::factory()->create(['name' => 'Two Levels Down', 'manager_id' => $dev->id]);

    $response = actingAs(chainViewer())->get(route('directory.show', $cara));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('directory/Show')
        ->where('reportingChain.above', [
            ['id' => $top->id, 'name' => 'Ada Top', 'job_title' => 'CEO', 'department' => 'Board', 'status' => 'current', 'link' => 'directory'],
            ['id' => $ben->id, 'name' => 'Ben Manager', 'job_title' => null, 'department' => 'Product', 'status' => 'current', 'link' => 'directory'],
        ])
        ->where('reportingChain.employee', ['id' => $cara->id, 'name' => 'Cara Report', 'job_title' => null, 'department' => 'Design', 'status' => 'current', 'link' => 'directory'])
        ->where('reportingChain.below.0.id', $dev->id)
        ->where('reportingChain.below.1.id', $zoe->id)
        ->has('reportingChain.below', 2));
});

it('shows the top Employee with no one above, and an Employee who is no one\'s Manager with no one below', function () {
    $top = Employee::factory()->top()->create();
    $cara = Employee::factory()->create(['manager_id' => $top->id]);

    actingAs(chainViewer())->get(route('directory.show', $top))
        ->assertInertia(fn (Assert $page) => $page
            ->where('reportingChain.above', [])
            ->where('reportingChain.below.0.id', $cara->id));
    actingAs(chainViewer())->get(route('directory.show', $cara))
        ->assertInertia(fn (Assert $page) => $page->where('reportingChain.below', []));
});

it('skips Managers who have left, going straight to the next one up who hasn\'t', function () {
    $top = Employee::factory()->top()->create();
    $gone = Employee::factory()->former()->create(['manager_id' => $top->id]);
    $ben = Employee::factory()->create(['manager_id' => $gone->id]);
    $alsoGone = Employee::factory()->former()->create(['manager_id' => $ben->id]);
    $cara = Employee::factory()->create(['manager_id' => $alsoGone->id]);

    actingAs(chainViewer())->get(route('directory.show', $cara))
        ->assertInertia(fn (Assert $page) => $page
            ->has('reportingChain.above', 2)
            ->where('reportingChain.above.0.id', $top->id)
            ->where('reportingChain.above.1.id', $ben->id));
});

it('lists below an Employee those whose Manager has left and who now report to them, plus Future Employees, but no Former ones', function () {
    $ben = Employee::factory()->top()->create();
    $gail = Employee::factory()->former()->create(['manager_id' => $ben->id]);
    $finn = Employee::factory()->create(['name' => 'Finn', 'manager_id' => $gail->id]);
    $dev = Employee::factory()->future()->create(['name' => 'Dev', 'manager_id' => $ben->id]);
    $cara = Employee::factory()->create(['name' => 'Cara', 'manager_id' => $ben->id]);

    actingAs(chainViewer())->get(route('directory.show', $ben))
        ->assertInertia(fn (Assert $page) => $page
            ->has('reportingChain.below', 3)
            ->where('reportingChain.below.0.id', $cara->id)
            ->where('reportingChain.below.1', fn ($member): bool => $member['id'] === $dev->id && $member['status'] === 'future')
            ->where('reportingChain.below.2.id', $finn->id));
});

it('shows a Future Employee\'s Reporting Chain', function () {
    $top = Employee::factory()->top()->create();
    $newcomer = Employee::factory()->future()->create(['manager_id' => $top->id]);

    actingAs(chainViewer())->get(route('directory.show', $newcomer))
        ->assertInertia(fn (Assert $page) => $page
            ->where('reportingChain.employee.status', 'future')
            ->has('reportingChain.above', 1));
});

it('links each member to their Employee page when the viewer may open it, and to their Directory page otherwise', function () {
    $top = Employee::factory()->top()->create();
    $ben = employeeWith([Permission::ViewDirectory->value => null, Permission::ViewEmployeeRecords->value => Scope::Reports], ['manager_id' => $top->id]);
    $cara = Employee::factory()->create(['manager_id' => $ben->id]);

    actingAs($ben)->get(route('directory.show', $ben))
        ->assertInertia(fn (Assert $page) => $page
            ->where('reportingChain.above.0.link', 'directory')
            ->where('reportingChain.employee.link', 'directory')
            ->where('reportingChain.below.0.link', 'employee'));
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
