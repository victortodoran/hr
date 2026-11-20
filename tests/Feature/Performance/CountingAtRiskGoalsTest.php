<?php

use App\Actions\Performance\AssessPerformanceEvaluation;
use App\Enums\Outcome;
use App\Enums\Permission;
use App\Enums\Progress;
use App\Enums\Scope;
use App\Http\Controllers\Performance\MyPerformanceEvaluationController;
use App\Http\Controllers\Performance\PerformanceEvaluationController;
use App\Models\Builders\PerformanceEvaluationBuilder;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(MyPerformanceEvaluationController::class, PerformanceEvaluationController::class, PerformanceEvaluationBuilder::class, PerformanceEvaluation::class);

beforeEach(fn () => travelTo('2026-12-01 09:00'));

/**
 * Ben, Cara (two Goals At Risk, one On Track), Lia (none At Risk) and Cara's Evaluation.
 *
 * @return array{Employee, Employee, Employee, PerformanceEvaluation}
 */
function atRiskLine(): array
{
    $ben = managerBen();
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara']);
    $lia = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Lia']);
    $caras = PerformanceEvaluation::factory()->of($cara)->create();
    Goal::factory()->for($caras)->count(2)->create(['progress' => Progress::AtRisk]);
    Goal::factory()->for($caras)->create(['progress' => Progress::OnTrack]);
    Goal::factory()->for(PerformanceEvaluation::factory()->of($lia))->create(['progress' => Progress::Done]);

    return [$ben, $cara, $lia, $caras];
}

/**
 * Null for a row with no Evaluation.
 *
 * @param  array<string, int|null>  $expected
 */
function assertAtRiskCountsOnList(Employee $viewer, array $expected): void
{
    actingAs($viewer)->get(route('performance-evaluations.index'))
        ->assertInertia(fn (Assert $page) => $page->where('employees.data', fn (Collection $rows): bool => $rows
            ->mapWithKeys(fn (array $row): array => [$row['name'] => $row['evaluation']['at_risk_goals_count'] ?? null])
            ->only(array_keys($expected))
            ->all() === $expected));
}

it('counts the At Risk Goals of each Open Evaluation on the Performance Evaluations page', function () {
    [$ben] = atRiskLine();

    assertAtRiskCountsOnList($ben, ['Cara' => 2, 'Lia' => 0]);
});

it('shows a reader the same counts on the rows they already see', function () {
    atRiskLine();
    $eve = employeeWith([Permission::ViewPerformanceEvaluations->value => Scope::Everyone], ['name' => 'Eve', 'start_date' => '2025-01-06']);

    assertAtRiskCountsOnList($eve, ['Cara' => 2, 'Lia' => 0]);
});

it('counts the Employee\'s own At Risk Goals of a past year on My Performance Evaluations', function () {
    [, $cara] = atRiskLine();
    travelTo('2027-01-04 09:00');

    actingAs($cara)->get(route('my-performance-evaluations.index'))
        ->assertInertia(fn (Assert $page) => $page->where('evaluations.0.at_risk_goals_count', 2));
});

it('follows the Goals\' current Progress', function () {
    [$ben, , , $caras] = atRiskLine();
    $caras->goals()->first()?->forceFill(['progress' => Progress::OnTrack])->save();

    assertAtRiskCountsOnList($ben, ['Cara' => 1]);
});

it('never counts At Risk Goals on a Completed Evaluation', function () {
    [$ben, $cara, , $caras] = atRiskLine();
    $caras->goals()->update(['outcome' => Outcome::NotMet]);
    app(AssessPerformanceEvaluation::class)->writeSummary($caras, 'A hard year.');
    app(AssessPerformanceEvaluation::class)->complete($caras, Outcome::PartlyMet);

    assertAtRiskCountsOnList($ben, ['Cara' => 0]);
    travelTo('2027-01-04 09:00');
    actingAs($cara)->get(route('my-performance-evaluations.index'))
        ->assertInertia(fn (Assert $page) => $page->where('evaluations.0.at_risk_goals_count', 0));
});
