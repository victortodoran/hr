<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\Performance\PerformanceEvaluationController;
use App\Http\Requests\Performance\PerformanceEvaluationListRequest;
use App\Models\Builders\EmployeeBuilder;
use App\Models\Employee;
use App\Models\PerformanceEvaluation;
use App\Pages\Performance\PerformanceEvaluationListPage;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(PerformanceEvaluationController::class, PerformanceEvaluationListRequest::class, PerformanceEvaluationListPage::class, EmployeeBuilder::class);

beforeEach(fn () => travelTo('2026-12-01 09:00'));

/**
 * Ben's Reports for 2026: Cara without an Evaluation, Dan with an Open
 * one, Eva with a Completed one whose Outcome is Met, and Ola, who left
 * without one; and Zed, who has no Manager.
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
 * Each expected row is its name, its Evaluation's state and its Outcome.
 *
 * @param  array<string, string>  $query
 * @param  array<mixed>  $expected
 */
function assertEvaluationsListRows(Employee $viewer, array $query, array $expected): void
{
    actingAs($viewer)->get(route('performance-evaluations.index', $query))
        ->assertInertia(fn (Assert $page) => $page->where('employees.data', fn (Collection $rows): bool => $rows
            ->map(fn (array $row): array => [$row['name'], $row['evaluation']['state'], $row['evaluation']['outcome']])
            ->all() === $expected));
}

it('lists only the Manager\'s Reports who have an Evaluation for the year, not those still without one', function () {
    [$ben] = evaluationsListLine();

    assertEvaluationsListRows($ben, [], [
        ['Dan', 'open', null],
        ['Eva', 'completed', 'met'],
    ]);
});

it('shows a reader the Evaluations they may read, and an Outcome only once Completed', function () {
    evaluationsListLine();
    $dana = employeeWith([Permission::ViewPerformanceEvaluations->value => Scope::Everyone], ['name' => 'Dana']);

    assertEvaluationsListRows($dana, [], [
        ['Dan', 'open', null],
        ['Eva', 'completed', 'met'],
    ]);
});

it('lists only the Employees with an Evaluation for a past or the next year', function () {
    [$ben, $cara, $dan] = evaluationsListLine();
    PerformanceEvaluation::factory()->of($dan)->create(['year' => 2025]);
    PerformanceEvaluation::factory()->of($cara)->create(['year' => 2027]);

    assertEvaluationsListRows($ben, ['year' => '2025'], [
        ['Dan', 'open', null],
    ]);
    assertEvaluationsListRows($ben, ['year' => '2027'], [
        ['Cara', 'open', null],
    ]);
});

it('narrows the list to one state and to part of a name', function (array $query, array $expected) {
    [$ben] = evaluationsListLine();

    assertEvaluationsListRows($ben, $query, $expected);
})->with([
    'Completed' => [['state' => 'completed'], [['Eva', 'completed', 'met']]],
    'Open' => [['state' => 'open'], [['Dan', 'open', null]]],
    'a name' => [['search' => 'an'], [['Dan', 'open', null]]],
    'a name with no match' => [['search' => 'Eva', 'state' => 'open'], []],
]);

it('narrows the list to one Employee the viewer may open the Evaluations of, giving them back for the filter bar, and ignores anyone else', function () {
    [$ben, , $dan] = evaluationsListLine();
    $zed = Employee::query()->where('name', 'Zed')->sole();

    assertEvaluationsListRows($ben, ['employee' => (string) $dan->id], [['Dan', 'open', null]]);
    actingAs($ben)->get(route('performance-evaluations.index', ['employee' => $dan->id]))
        ->assertInertia(fn (Assert $page) => $page->where('employee', ['id' => $dan->id, 'name' => 'Dan']));
    actingAs($ben)->get(route('performance-evaluations.index', ['employee' => $zed->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('employee', null)
            ->where('employees.data', fn (Collection $rows): bool => $rows->pluck('name')->all() === ['Dan', 'Eva']));
    actingAs($ben)->get(route('performance-evaluations.index', ['employee' => 'cara']))->assertSessionHasErrors('employee');
});

it('sends the filters back for the filter bar, and shows any state for an unknown one', function (string $unknownState) {
    [$ben] = evaluationsListLine();

    actingAs($ben)->get(route('performance-evaluations.index', ['search' => 'Ev', 'state' => 'completed']))
        ->assertInertia(fn (Assert $page) => $page->where('search', 'Ev')->where('state', 'completed'));
    actingAs($ben)->get(route('performance-evaluations.index'))
        ->assertInertia(fn (Assert $page) => $page->where('search', '')->where('state', null));
    actingAs($ben)->get(route('performance-evaluations.index', ['state' => $unknownState]))
        ->assertSessionHasNoErrors()
        ->assertInertia(fn (Assert $page) => $page
            ->where('state', null)
            ->where('employees.data', fn (Collection $rows): bool => $rows->pluck('name')->all() === ['Dan', 'Eva']));
})->with(['the old None yet' => 'none-yet', 'a made-up one' => 'pending']);
