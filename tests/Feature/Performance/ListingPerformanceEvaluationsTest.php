<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\Performance\PerformanceEvaluationController;
use App\Http\Requests\Performance\PerformanceEvaluationListRequest;
use App\Models\Builders\EmployeeBuilder;
use App\Models\Employee;
use App\Models\PerformanceEvaluation;
use App\Pages\Performance\PerformanceEvaluationFormPage;
use App\Pages\Performance\PerformanceEvaluationListPage;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(PerformanceEvaluationController::class, PerformanceEvaluationListRequest::class, PerformanceEvaluationListPage::class, PerformanceEvaluationFormPage::class, EmployeeBuilder::class);

beforeEach(fn () => travelTo('2026-12-01 09:00'));

/**
 * Ben's Reports for 2026: Cara with none yet, Dan with an Open one, Eva
 * with a Completed one whose Outcome is Met, and Ola, who left without
 * one; and Zed, who has no Manager.
 *
 * @return array{Employee, Employee, Employee, Employee}
 */
function evaluationsListLine(): array
{
    $ben = managerBen();
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara']);
    $dan = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Dan']);
    $eva = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Eva']);
    Employee::factory()->reportingTo($ben)->create(['name' => 'Ola', 'start_date' => '2020-01-06', 'leaving_date' => '2026-10-31']);
    Employee::factory()->startedLongAgo()->create(['name' => 'Zed']);
    PerformanceEvaluation::factory()->of($dan)->create(['outcome' => 'met']);
    PerformanceEvaluation::factory()->of($eva)->completed()->create(['outcome' => 'met']);

    return [$ben, $cara, $dan, $eva];
}

/**
 * Assert the page's rows for the viewer, each as its name, state
 * ("none-yet" without an Evaluation), Outcome and whether the viewer may
 * create it.
 *
 * @param  array<string, string>  $query
 * @param  array<mixed>  $expected
 */
function assertEvaluationsListRows(Employee $viewer, array $query, array $expected): void
{
    actingAs($viewer)->get(route('performance-evaluations.index', $query))
        ->assertInertia(fn (Assert $page) => $page->where('employees.data', fn (Collection $rows): bool => $rows
            ->map(fn (array $row): array => [$row['name'], $row['evaluation']['state'] ?? 'none-yet', $row['evaluation']['outcome'] ?? null, $row['can_create']])
            ->all() === $expected));
}

it('lists the Manager\'s Reports without an Evaluation as None yet, with Create, but not those without a Manager or who left without one', function () {
    [$ben] = evaluationsListLine();

    assertEvaluationsListRows($ben, [], [
        ['Cara', 'none-yet', null, true],
        ['Dan', 'open', null, false],
        ['Eva', 'completed', 'met', false],
    ]);
});

it('shows a reader None yet without Create, and an Outcome only once Completed', function () {
    evaluationsListLine();
    $dana = employeeWith([Permission::ViewPerformanceEvaluations->value => Scope::Everyone], ['name' => 'Dana']);

    assertEvaluationsListRows($dana, [], [
        ['Ben', 'none-yet', null, false],
        ['Cara', 'none-yet', null, false],
        ['Dan', 'open', null, false],
        ['Eva', 'completed', 'met', false],
    ]);
});

it('lists only the Employees with an Evaluation for a past year, without None yet rows', function () {
    [$ben, , $dan] = evaluationsListLine();
    PerformanceEvaluation::factory()->of($dan)->create(['year' => 2025]);

    assertEvaluationsListRows($ben, ['year' => '2025'], [
        ['Dan', 'open', null, false],
    ]);
    assertEvaluationsListRows($ben, ['year' => '2027'], [
        ['Cara', 'none-yet', null, true],
        ['Dan', 'none-yet', null, true],
        ['Eva', 'none-yet', null, true],
    ]);
});

it('narrows the list to one state and to part of a name', function (array $query, array $expected) {
    [$ben] = evaluationsListLine();

    assertEvaluationsListRows($ben, $query, $expected);
})->with([
    'Completed' => [['state' => 'completed'], [['Eva', 'completed', 'met', false]]],
    'Open' => [['state' => 'open'], [['Dan', 'open', null, false]]],
    'None yet' => [['state' => 'none-yet'], [['Cara', 'none-yet', null, true]]],
    'a name' => [['search' => 'ar'], [['Cara', 'none-yet', null, true]]],
    'a name with no match' => [['search' => 'Eva', 'state' => 'open'], []],
]);

it('narrows the list to one Employee the viewer may open the Evaluations of, giving them back for the filter bar, and ignores anyone else', function () {
    [$ben, $cara] = evaluationsListLine();
    $zed = Employee::query()->where('name', 'Zed')->sole();

    assertEvaluationsListRows($ben, ['employee' => (string) $cara->id], [['Cara', 'none-yet', null, true]]);
    actingAs($ben)->get(route('performance-evaluations.index', ['employee' => $cara->id]))
        ->assertInertia(fn (Assert $page) => $page->where('employee', ['id' => $cara->id, 'name' => 'Cara']));
    actingAs($ben)->get(route('performance-evaluations.index', ['employee' => $zed->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('employee', null)
            ->where('employees.data', fn (Collection $rows): bool => $rows->pluck('name')->all() === ['Cara', 'Dan', 'Eva']));
    actingAs($ben)->get(route('performance-evaluations.index', ['employee' => 'cara']))->assertSessionHasErrors('employee');
});

it('sends the filters back for the filter bar and refuses an unknown state', function () {
    [$ben] = evaluationsListLine();

    actingAs($ben)->get(route('performance-evaluations.index', ['search' => 'Ca', 'state' => 'none-yet']))
        ->assertInertia(fn (Assert $page) => $page->where('search', 'Ca')->where('state', 'none-yet'));
    actingAs($ben)->get(route('performance-evaluations.index'))
        ->assertInertia(fn (Assert $page) => $page->where('search', '')->where('state', null));
    actingAs($ben)->get(route('performance-evaluations.index', ['state' => 'pending']))->assertSessionHasErrors('state');
});

it('opens the New Performance Evaluation page with the Employee and year from the list\'s Create, when they may get one', function () {
    [$ben, $cara, $dan] = evaluationsListLine();

    actingAs($ben)->get(route('performance-evaluations.create', ['year' => 2027, 'employee_id' => $cara->id]))
        ->assertInertia(fn (Assert $page) => $page->where('chosenYear', 2027)->where('chosenEmployeeId', $cara->id));
    actingAs($ben)->get(route('performance-evaluations.create', ['year' => 2026, 'employee_id' => $dan->id]))
        ->assertInertia(fn (Assert $page) => $page->where('chosenYear', 2026)->where('chosenEmployeeId', null));
    actingAs($ben)->get(route('performance-evaluations.create'))
        ->assertInertia(fn (Assert $page) => $page->where('chosenYear', 2026)->where('chosenEmployeeId', null));
});
