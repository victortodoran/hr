<?php

use App\Enums\Outcome;
use App\Enums\PerformanceEvaluationState;
use App\Http\Controllers\Performance\PerformanceEvaluationAssessmentController;
use App\Http\Controllers\Performance\PerformanceEvaluationCompletionController;
use App\Http\Controllers\Performance\PerformanceEvaluationController;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(PerformanceEvaluationController::class, PerformanceEvaluationAssessmentController::class, PerformanceEvaluationCompletionController::class);

beforeEach(fn () => travelTo('2026-12-01 09:00'));

it('saves Outcomes, completes the Evaluation, and the Employee then reads them', function () {
    $ben = managerBen();
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara']);
    $evaluation = PerformanceEvaluation::factory()->of($cara)->create();
    Goal::factory()->for($evaluation)->create(['title' => 'Lead the Checkout API']);

    actingAs($ben);

    visit(route('performance-evaluations.show', $evaluation))
        ->click('[aria-label="Outcome of Lead the Checkout API"]')
        ->click('[role="option"] >> text=Exceeded')
        ->click('Save Outcomes')
        ->assertSee('Outcomes saved.')
        ->click('#evaluation-outcome')
        ->click('internal:role=option[name="Met"s]')
        ->fill('#evaluation-summary', "A strong year.\nThe Relaunch shipped on time.")
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
        ->assertSee('Exceeded')
        ->assertSee('A strong year.')
        ->assertNoJavaScriptErrors();

    $evaluation->refresh();
    expect($evaluation->state)->toBe(PerformanceEvaluationState::Completed)
        ->and($evaluation->outcome)->toBe(Outcome::Met);
});
