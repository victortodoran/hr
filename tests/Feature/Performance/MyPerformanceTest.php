<?php

use App\Http\Controllers\Performance\GoalProgressController;
use App\Http\Controllers\Performance\MyPerformanceEvaluationController;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;
use App\Pages\Performance\MyPerformanceEvaluationsPage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(MyPerformanceEvaluationController::class, MyPerformanceEvaluationsPage::class, GoalProgressController::class);

beforeEach(fn () => travelTo('2026-12-01 09:00'));

function caraOnMyPerformance(): Employee
{
    return Employee::factory()->startedLongAgo()->reportingTo(managerBen())->create(['name' => 'Cara']);
}

it('opens on this year\'s Performance Evaluation with its Goals, whose Progress the Employee may change, and lists the other years below', function () {
    $cara = caraOnMyPerformance();
    $older = PerformanceEvaluation::factory()->of($cara)->completed()->create(['year' => 2024]);
    $stillOpen = PerformanceEvaluation::factory()->of($cara)->create(['year' => 2025]);
    $current = PerformanceEvaluation::factory()->of($cara)->create(['year' => 2026]);
    $goal = Goal::factory()->for($current)->create(['title' => 'Lead the Checkout API', 'description' => 'Own its design.']);
    PerformanceEvaluation::factory()->create();

    actingAs($cara)->get(route('my-performance-evaluations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('performance-evaluations/Mine')
            ->where('year', 2026)
            ->where('current.evaluation.id', $current->id)
            ->where('current.evaluation.goals', [['id' => $goal->id, 'title' => 'Lead the Checkout API', 'description' => 'Own its design.', 'progress' => 'not-started', 'comments_count' => 0, 'outcome' => null]])
            ->where('current.isOwn', true)
            ->where('current.canEdit', false)
            ->where('current.canChangeProgress', true)
            ->where('current.canSeeOutcomes', false)
            ->where('evaluations', [
                ['id' => $stillOpen->id, 'year' => 2025, 'state' => 'open', 'goals_count' => 0, 'at_risk_goals_count' => 0, 'outcome' => null],
                ['id' => $older->id, 'year' => 2024, 'state' => 'completed', 'goals_count' => 2, 'at_risk_goals_count' => 0, 'outcome' => 'met'],
            ]));
});

it('leads a Progress change made from it back to it', function () {
    $cara = caraOnMyPerformance();
    $goal = Goal::factory()->for(PerformanceEvaluation::factory()->of($cara))->create();

    actingAs($cara)
        ->from(route('my-performance-evaluations.index'))
        ->put(route('performance-evaluations.goals.progress.update', [$goal->performanceEvaluation, $goal]), ['progress' => 'on-track'])
        ->assertRedirect(route('my-performance-evaluations.index'));
});

it('shows this year\'s Outcome and summary once Completed', function () {
    $cara = caraOnMyPerformance();
    PerformanceEvaluation::factory()->of($cara)->completed()->create(['summary' => 'A steady year.']);

    actingAs($cara)->get(route('my-performance-evaluations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('current.canSeeOutcomes', true)
            ->where('current.canChangeProgress', false)
            ->where('current.evaluation.outcome', 'met')
            ->where('current.evaluation.summary', 'A steady year.')
            ->where('evaluations', []));
});

it('has no Evaluation on top while this year has none, and still lists the past years', function () {
    $cara = caraOnMyPerformance();
    $past = PerformanceEvaluation::factory()->of($cara)->create(['year' => 2025]);

    actingAs($cara)->get(route('my-performance-evaluations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('year', 2026)
            ->where('current', null)
            ->where('evaluations.0.id', $past->id)
            ->count('evaluations', 1));
});
