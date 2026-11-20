<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\Search\CommandMenuController;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Project;
use App\Pages\Search\CommandMenuResults;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

covers(CommandMenuController::class, CommandMenuResults::class);

/**
 * The names in one group of the command menu's results for the search.
 *
 * @return list<string>
 */
function commandMenuNames(Employee $viewer, string $search, string $group): array
{
    return array_column(actingAs($viewer)->getJson(route('search', ['search' => $search]))->assertOk()->json($group), 'name');
}

it('finds only the viewer themselves among people when nothing else lets them open anyone', function () {
    $cara = Employee::factory()->startedLongAgo()->create(['name' => 'Cara Lane']);
    Employee::factory()->startedLongAgo()->create(['name' => 'Carl Moss']);

    actingAs($cara)->getJson(route('search', ['search' => 'car']))
        ->assertOk()
        ->assertExactJson([
            'people' => [['id' => $cara->id, 'name' => 'Cara Lane', 'job_title' => null, 'canViewEmployee' => true]],
            'projects' => [],
            'clients' => [],
        ]);
});

it('finds everyone the Directory lists for a viewer with view directory, opening them in the Directory, but no Former or Future Employees', function () {
    $viewer = employeeWith([Permission::ViewDirectory->value => null], ['name' => 'Vera Viewer']);
    $carl = Employee::factory()->startedLongAgo()->create(['name' => 'Carl Moss']);
    Employee::factory()->former()->create(['name' => 'Carla Gone']);
    Employee::factory()->future()->create(['name' => 'Carlo Soon']);

    actingAs($viewer)->getJson(route('search', ['search' => 'carl']))
        ->assertOk()
        ->assertJsonPath('people', [['id' => $carl->id, 'name' => 'Carl Moss', 'job_title' => null, 'canViewEmployee' => false]]);
});

it('finds the people whose Employee page the viewer may open, opening it, and leaves out Former Employees and those outside their Scope', function () {
    $ben = managerBen();
    giveRole($ben, [Permission::ViewEmployeeRecords->value => Scope::Reports]);
    $report = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Dana Report']);
    $futureReport = Employee::factory()->future()->reportingTo($ben)->create(['name' => 'Dane Soon']);
    Employee::factory()->former()->reportingTo($ben)->create(['name' => 'Dani Gone']);
    Employee::factory()->startedLongAgo()->create(['name' => 'Dan Elsewhere']);

    actingAs($ben)->getJson(route('search', ['search' => 'dan']))
        ->assertOk()
        ->assertJsonPath('people', [
            ['id' => $report->id, 'name' => 'Dana Report', 'job_title' => null, 'canViewEmployee' => true],
            ['id' => $futureReport->id, 'name' => 'Dane Soon', 'job_title' => null, 'canViewEmployee' => true],
        ]);
});

it('finds people whose page seeing their Assignments or Time Off opens', function (string $permission) {
    $ben = managerBen();
    giveRole($ben, [$permission => Scope::Reports]);
    Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Dan Report']);
    Employee::factory()->startedLongAgo()->create(['name' => 'Dan Elsewhere']);

    expect(commandMenuNames($ben, 'dan', 'people'))->toBe(['Dan Report']);
})->with([Permission::ViewPay->value, Permission::ViewTimeOff->value]);

it('finds Projects only for viewers who may see the Projects', function () {
    Project::factory()->forClient(Client::factory()->create(['name' => 'Globex']))->create(['name' => 'Relaunch']);

    expect(commandMenuNames(Employee::factory()->create(), 'relaunch', 'projects'))->toBe([]);

    actingAs(employeeWith([Permission::ViewProjects->value => null]))
        ->getJson(route('search', ['search' => 'relaunch']))
        ->assertJsonPath('projects.0.name', 'Relaunch')
        ->assertJsonPath('projects.0.client', 'Globex');
});

it('finds Clients only for viewers who manage them', function () {
    Client::factory()->create(['name' => 'Globex']);

    expect(commandMenuNames(employeeWith([Permission::ViewProjects->value => null]), 'glob', 'clients'))->toBe([])
        ->and(commandMenuNames(employeeWith([Permission::ManageClients->value => null]), 'glob', 'clients'))->toBe(['Globex']);
});

it('shows at most five results in each group, by name', function () {
    $viewer = employeeWith([Permission::ViewDirectory->value => null, Permission::ViewProjects->value => null, Permission::ManageClients->value => null], ['name' => 'Vera Viewer']);
    foreach (['Flo', 'Fay', 'Fred', 'Finn', 'Fia', 'Fox'] as $name) {
        Employee::factory()->startedLongAgo()->create(['name' => "{$name} F"]);
        Project::factory()->create(['name' => "{$name} Project"]);
        Client::factory()->create(['name' => "{$name} Client"]);
    }

    expect(commandMenuNames($viewer, 'f', 'people'))->toBe(['Fay F', 'Fia F', 'Finn F', 'Flo F', 'Fox F'])
        ->and(commandMenuNames($viewer, 'f', 'projects'))->toHaveCount(5)
        ->and(commandMenuNames($viewer, 'f', 'clients'))->toHaveCount(5);
});

it('finds nothing for an empty search', function () {
    actingAs(employeeHoldingAdmin())->getJson(route('search', ['search' => '  ']))
        ->assertExactJson(['people' => [], 'projects' => [], 'clients' => []]);
});

it('asks a guest to log in', function () {
    get(route('search', ['search' => 'a']))->assertRedirect(route('login'));
});
