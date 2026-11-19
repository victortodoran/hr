<?php

use App\Enums\Progress;
use App\Http\Controllers\Performance\MyPerformanceEvaluationController;
use App\Http\Controllers\Performance\PerformanceEvaluationController;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(PerformanceEvaluationController::class, MyPerformanceEvaluationController::class);

beforeEach(fn () => travelTo('2026-12-01 09:00'));

it('shows the At Risk count beside an Open Evaluation on both lists, the Employee\'s once it is a past year', function () {
    $ben = managerBen();
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara']);
    Goal::factory()->for(PerformanceEvaluation::factory()->of($cara))->create(['progress' => Progress::AtRisk]);

    actingAs($ben);

    visit(route('performance-evaluations.index'))
        ->assertSee('1 At Risk')
        ->assertNoJavaScriptErrors();

    actingAs($cara);
    travelTo('2027-01-04 09:00');

    visit(route('my-performance-evaluations.index'))
        ->assertSee('1 At Risk')
        ->assertNoJavaScriptErrors();
});
