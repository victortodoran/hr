<?php

use App\Enums\Outcome;
use App\Enums\PerformanceEvaluationState;
use App\Enums\Progress;
use App\Http\Controllers\Performance\GoalOutcomeController;
use App\Http\Controllers\Performance\GoalProgressController;
use App\Http\Controllers\Performance\PerformanceEvaluationCompletionController;
use App\Http\Controllers\Performance\PerformanceEvaluationController;
use App\Http\Controllers\Performance\PerformanceEvaluationSummaryController;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(PerformanceEvaluationController::class, GoalOutcomeController::class, GoalProgressController::class, PerformanceEvaluationSummaryController::class, PerformanceEvaluationCompletionController::class);

beforeEach(fn () => travelTo('2026-12-01 09:00'));

it('saves Progress, Outcomes and the summary as they are given, completes the Evaluation by giving the year its Outcome, and the Employee then reads them', function () {
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
        ->assertSee('Give every Goal an Outcome and write the summary to complete it.')
        ->assertAttribute('#evaluation-outcome', 'data-disabled', '')
        ->fill('#evaluation-summary', "A strong year.\nThe Relaunch shipped on time.")
        ->click('internal:role=heading[name="The year as a whole"s]')
        ->assertDontSee('Give every Goal an Outcome and write the summary to complete it.')
        ->click('#evaluation-outcome')
        ->click('internal:role=option[name="Met"s]')
        ->assertSee('Complete Cara\'s 2026 Performance Evaluation as Met?')
        ->assertSee('Cara sees the Outcomes and summary.')
        ->click('Keep it Open')
        ->assertSeeIn('#evaluation-outcome', 'Not given')
        ->click('#evaluation-outcome')
        ->click('internal:role=option[name="Met"s]')
        ->click('internal:role=dialog >> internal:role=button[name="Complete"s]')
        ->assertSee('Performance Evaluation completed.')
        ->assertSee('A strong year.')
        ->assertSee('The Relaunch shipped on time.')
        ->assertMissing('#evaluation-outcome')
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

it('gives a Goal its Outcome from the Goal\'s own page, staying on it', function () {
    $ben = managerBen();
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara']);
    $evaluation = PerformanceEvaluation::factory()->of($cara)->create();
    $goal = Goal::factory()->for($evaluation)->create(['title' => 'Lead the Checkout API']);

    actingAs($ben);

    visit(route('performance-evaluations.goals.show', [$evaluation, $goal]))
        ->assertSeeIn('[aria-label="Outcome"]', 'Not given')
        ->click('[aria-label="Outcome"]')
        ->click('internal:role=option[name="Met"s]')
        ->assertSee('Saved')
        ->assertSee('The Goal was given')
        ->assertSeeIn('[aria-label="Outcome"]', 'Met')
        ->assertNoJavaScriptErrors();

    expect($goal->fresh()?->outcome)->toBe(Outcome::Met);
});
