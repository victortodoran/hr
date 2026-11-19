<?php

use App\Enums\Outcome;
use App\Enums\PerformanceEvaluationState;
use App\Enums\Progress;
use App\Http\Controllers\Performance\GoalOutcomeController;
use App\Http\Controllers\Performance\GoalProgressController;
use App\Http\Controllers\Performance\PerformanceEvaluationAssessmentController;
use App\Http\Controllers\Performance\PerformanceEvaluationCompletionController;
use App\Http\Controllers\Performance\PerformanceEvaluationController;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(PerformanceEvaluationController::class, GoalOutcomeController::class, GoalProgressController::class, PerformanceEvaluationAssessmentController::class, PerformanceEvaluationCompletionController::class);

beforeEach(fn () => travelTo('2026-12-01 09:00'));

it('saves Progress and Outcomes, completes the Evaluation, and the Employee then reads them', function () {
    $ben = managerBen();
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara']);
    $evaluation = PerformanceEvaluation::factory()->of($cara)->create();
    Goal::factory()->for($evaluation)->create(['title' => 'Lead the Checkout API']);

    actingAs($ben);

    visit(route('performance-evaluations.show', $evaluation))
        ->click('[aria-label="Progress of Lead the Checkout API"]')
        ->click('internal:role=option[name="Done"s]')
        ->assertSee('Saved')
        ->click('[aria-label="Outcome of Lead the Checkout API"]')
        ->click('internal:role=option[name="Exceeded"s]')
        ->assertSee('Saved')
        ->click('#evaluation-outcome')
        ->click('internal:role=option[name="Met"s]')
        ->fill('#evaluation-summary', "A strong year.\nThe Relaunch shipped on time.")
        ->click('Save Outcomes')
        ->assertSee('Outcomes saved.')
        ->click('internal:role=button[name="Complete"s]')
        ->assertSee('Complete Cara\'s 2026 Performance Evaluation?')
        ->click('internal:role=dialog >> internal:role=button[name="Complete"s]')
        ->assertSee('Performance Evaluation completed.')
        ->assertSee('A strong year.')
        ->assertSee('The Relaunch shipped on time.')
        ->assertDontSee('Save Outcomes')
        ->assertNoJavaScriptErrors();

    actingAs($cara);

    visit(route('performance-evaluations.show', $evaluation))
        ->assertSee('Completed')
        ->assertSee('The year as a whole')
        ->assertSee('Exceeded')
        ->assertSee('A strong year.')
        ->assertNoJavaScriptErrors();

    $evaluation->refresh();
    expect($evaluation->state)->toBe(PerformanceEvaluationState::Completed)
        ->and($evaluation->outcome)->toBe(Outcome::Met)
        ->and($evaluation->goals()->sole()->outcome)->toBe(Outcome::Exceeded)
        ->and($evaluation->goals()->sole()->progress)->toBe(Progress::Done);
});

it('puts a Goal\'s Outcome back with a toast saying why when the Evaluation was Completed meanwhile, and clears one', function () {
    $ben = managerBen();
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara']);
    $evaluation = PerformanceEvaluation::factory()->of($cara)->create();
    $goal = Goal::factory()->for($evaluation)->create(['title' => 'Lead the Checkout API', 'outcome' => Outcome::Met]);

    actingAs($ben);

    $page = visit(route('performance-evaluations.show', $evaluation))
        ->click('[aria-label="Outcome of Lead the Checkout API"]')
        ->click('internal:role=option[name="Not given"s]')
        ->assertSee('Saved');

    expect($goal->fresh()?->outcome)->toBeNull();

    Goal::query()->whereKey($goal->id)->update(['outcome' => Outcome::Met]);
    PerformanceEvaluation::query()->whereKey($evaluation->id)->update(['state' => PerformanceEvaluationState::Completed, 'completed_at' => now(), 'outcome' => Outcome::Met, 'summary' => 'Good year.']);

    $page->click('[aria-label="Outcome of Lead the Checkout API"]')
        ->click('internal:role=option[name="Exceeded"s]')
        ->assertSee('The Outcome wasn’t saved')
        ->assertMissing('[aria-label="Outcome of Lead the Checkout API"]')
        ->assertNoJavaScriptErrors();

    expect($goal->fresh()?->outcome)->toBe(Outcome::Met);
});
