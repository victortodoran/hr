<?php

use App\Enums\Progress;
use App\Http\Controllers\Performance\GoalProgressController;
use App\Http\Controllers\Performance\MyPerformanceEvaluationController;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(MyPerformanceEvaluationController::class, GoalProgressController::class);

beforeEach(fn () => travelTo('2026-12-01 09:00'));

it('opens on this year\'s Goals, whose Progress the Employee changes there, above the past years', function () {
    $cara = Employee::factory()->startedLongAgo()->reportingTo(managerBen())->create(['name' => 'Cara']);
    PerformanceEvaluation::factory()->of($cara)->completed()->create(['year' => 2025]);
    $goal = Goal::factory()->for(PerformanceEvaluation::factory()->of($cara))->create(['title' => 'Lead the Checkout API']);

    actingAs($cara);

    visit(route('my-performance-evaluations.index'))
        ->assertSee('Lead the Checkout API')
        ->click('[aria-label="Progress of Lead the Checkout API"]')
        ->click('internal:role=option[name="On Track"s]')
        ->assertSee('Saved')
        ->assertSee('Past years')
        ->assertSee('2025')
        ->assertNoJavaScriptErrors();

    expect($goal->fresh()?->progress)->toBe(Progress::OnTrack);
});

it('shows this year\'s Outcome and summary once Completed', function () {
    $cara = Employee::factory()->startedLongAgo()->reportingTo(managerBen())->create(['name' => 'Cara']);
    PerformanceEvaluation::factory()->of($cara)->completed()->create(['summary' => 'A steady year.']);

    actingAs($cara);

    visit(route('my-performance-evaluations.index'))
        ->assertSee('The year as a whole')
        ->assertSee('A steady year.')
        ->assertMissing('[aria-label^="Progress of"]')
        ->assertNoJavaScriptErrors();
});

it('says the Manager hasn\'t set this year\'s Goals yet while there is no Evaluation for it', function () {
    $cara = Employee::factory()->startedLongAgo()->reportingTo(managerBen())->create(['name' => 'Cara']);

    actingAs($cara);

    visit(route('my-performance-evaluations.index'))
        ->assertSee("Your Manager hasn't set your 2026 Goals yet.")
        ->assertSee('Your Performance Evaluations for other years appear here.')
        ->assertNoJavaScriptErrors();
});
