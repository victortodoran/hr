<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\Employees\DirectoryController;
use App\Models\Assignment;
use App\Models\Department;
use App\Models\Employee;
use App\Pages\Employees\DirectoryIndexPage;
use App\Support\ReportingChain;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(DirectoryController::class, DirectoryIndexPage::class, ReportingChain::class, Employee::class);

beforeEach(fn () => travelTo('2026-03-01 09:00'));

function chainViewer(): Employee
{
    return employeeWith([Permission::ViewDirectory->value => null]);
}

it('shows an Employee\'s Reporting Chain: everyone above up to one with no Manager, them, and those they are the Manager of', function () {
    $top = Employee::factory()->create(['name' => 'Ada Top', 'department_id' => Department::factory()->create(['name' => 'Board'])->id]);
    Assignment::factory()->for($top)->internal()->create(['job_title' => 'CEO']);
    $ben = Employee::factory()->reportingTo($top)->create(['name' => 'Ben Manager', 'department_id' => Department::factory()->create(['name' => 'Product'])->id]);
    $cara = Employee::factory()->reportingTo($ben)->create(['name' => 'Cara Report', 'department_id' => Department::factory()->create(['name' => 'Design'])->id]);
    $zoe = Employee::factory()->reportingTo($cara)->create(['name' => 'Zoe Below']);
    $dev = Employee::factory()->reportingTo($cara)->create(['name' => 'Dev Below']);
    Employee::factory()->reportingTo($dev)->create(['name' => 'Two Levels Down']);

    $response = actingAs(chainViewer())->get(route('directory.index', ['person' => $cara->id]));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('directory/Index')
        ->where('person.above', [
            ['id' => $top->id, 'name' => 'Ada Top', 'job_title' => 'CEO', 'department' => 'Board', 'status' => 'current', 'link' => 'directory'],
            ['id' => $ben->id, 'name' => 'Ben Manager', 'job_title' => null, 'department' => 'Product', 'status' => 'current', 'link' => 'directory'],
        ])
        ->where('person.employee', ['id' => $cara->id, 'name' => 'Cara Report', 'job_title' => null, 'department' => 'Design', 'status' => 'current', 'link' => 'directory'])
        ->where('person.below.0.id', $dev->id)
        ->where('person.below.1.id', $zoe->id)
        ->has('person.below', 2));
});

it('shows an Employee with no Manager with no one above, and an Employee who is no one\'s Manager with no one below', function () {
    $top = Employee::factory()->create();
    $cara = Employee::factory()->reportingTo($top)->create();

    actingAs(chainViewer())->get(route('directory.index', ['person' => $top->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('person.above', [])
            ->where('person.below.0.id', $cara->id));
    actingAs(chainViewer())->get(route('directory.index', ['person' => $cara->id]))
        ->assertInertia(fn (Assert $page) => $page->where('person.below', []));
});

it('skips Managers who have left, going straight to the next one up who hasn\'t', function () {
    $top = Employee::factory()->create();
    $gone = Employee::factory()->former()->reportingTo($top)->create();
    $ben = Employee::factory()->reportingTo($gone)->create();
    $alsoGone = Employee::factory()->former()->reportingTo($ben)->create();
    $cara = Employee::factory()->reportingTo($alsoGone)->create();

    actingAs(chainViewer())->get(route('directory.index', ['person' => $cara->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('person.above', 2)
            ->where('person.above.0.id', $top->id)
            ->where('person.above.1.id', $ben->id));
});

it('lists below an Employee those whose Manager has left and who now report to them, plus Future Employees, but no Former ones', function () {
    $ben = Employee::factory()->create();
    $gail = Employee::factory()->former()->reportingTo($ben)->create();
    $finn = Employee::factory()->reportingTo($gail)->create(['name' => 'Finn']);
    $dev = Employee::factory()->future()->reportingTo($ben)->create(['name' => 'Dev']);
    $cara = Employee::factory()->reportingTo($ben)->create(['name' => 'Cara']);

    actingAs(chainViewer())->get(route('directory.index', ['person' => $ben->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('person.below', 3)
            ->where('person.below.0.id', $cara->id)
            ->where('person.below.1', fn ($member): bool => $member['id'] === $dev->id && $member['status'] === 'future')
            ->where('person.below.2.id', $finn->id));
});

it('shows a Future Employee\'s Reporting Chain', function () {
    $top = Employee::factory()->create();
    $newcomer = Employee::factory()->future()->reportingTo($top)->create();

    actingAs(chainViewer())->get(route('directory.index', ['person' => $newcomer->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('person.employee.status', 'future')
            ->has('person.above', 1));
});

it('links each member to their Employee page when the viewer may open it, and to their Directory page otherwise', function () {
    $top = Employee::factory()->create();
    $ben = employeeWith([Permission::ViewDirectory->value => null, Permission::ViewEmployeeRecords->value => Scope::Reports], ['manager_id' => $top->id]);
    $cara = Employee::factory()->reportingTo($ben)->create();

    actingAs($ben)->get(route('directory.index', ['person' => $ben->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('person.above.0.link', 'directory')
            ->where('person.employee.link', 'employee')
            ->where('person.below.0.link', 'employee'));
});

it('opens no sheet for a Former Employee, who has left the directory, or for someone who does not exist', function () {
    $top = Employee::factory()->create();
    $gone = Employee::factory()->former()->reportingTo($top)->create();

    actingAs(chainViewer())->get(route('directory.index', ['person' => $gone->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('person', null));
    actingAs(chainViewer())->get(route('directory.index', ['person' => $gone->id + 1000]))
        ->assertInertia(fn (Assert $page) => $page->where('person', null));
});

it('opens no sheet for a Manager without view directory, who sees only their Direct Reports', function () {
    $ben = employeeWith([Permission::ManageDepartments->value => null]);
    $cara = Employee::factory()->reportingTo($ben)->create();

    actingAs($ben)->get(route('directory.index', ['tab' => 'reports', 'person' => $cara->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('tab', 'reports')->where('person', null));
});

it('sends the old Reporting Chain page to the Employee\'s sheet over the Directory', function () {
    $cara = Employee::factory()->create();

    actingAs(chainViewer())->get(route('directory.show', $cara))
        ->assertRedirect(route('directory.index', ['person' => $cara->id]));
});

it('has no Reporting Chain page for a Former Employee', function () {
    $top = Employee::factory()->create();
    $gone = Employee::factory()->former()->reportingTo($top)->create();

    actingAs(chainViewer())->get(route('directory.show', $gone))->assertNotFound();
});

it('refuses the old Reporting Chain page to an Employee without view directory', function () {
    $top = Employee::factory()->create();

    actingAs(employeeWith([Permission::ManageDepartments->value => null]))
        ->get(route('directory.show', $top))
        ->assertForbidden();
});

it('sends no sheet when nobody is asked for, and rejects a person that is not an id', function () {
    actingAs(chainViewer())->get(route('directory.index'))
        ->assertInertia(fn (Assert $page) => $page->where('person', null));
    actingAs(chainViewer())->get(route('directory.index', ['person' => 'cara']))
        ->assertSessionHasErrors('person');
});

it('reads the viewer\'s Roles and grants as often for a Reporting Chain of 15 as for one of 3', function () {
    $viewer = employeeWith([Permission::ViewDirectory->value => null, Permission::ViewEmployeeRecords->value => Scope::Reports, Permission::ViewAssignments->value => Scope::Own]);
    $accessReadsToOpen = function (int $length) use ($viewer): int {
        $manager = Employee::factory()->create();
        foreach (range(2, $length) as $level) {
            $manager = Employee::factory()->reportingTo($manager)->create();
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        actingAs(Employee::query()->findOrFail($viewer->id))->get(route('directory.index', ['person' => $manager->id]))->assertOk();

        return collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_contains($query['query'], '"roles"') || str_contains($query['query'], '"role_permissions"'))
            ->count();
    };

    $forThree = $accessReadsToOpen(3);

    expect($accessReadsToOpen(15))->toBe($forThree)
        ->and($forThree)->toBe(2);
});
