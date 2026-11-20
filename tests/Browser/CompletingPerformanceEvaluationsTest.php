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

it('saves Progress and Outcomes as they are picked and the summary with its button, completes the Evaluation by giving the year its Outcome, and the Employee then reads them', function () {
    $ben = managerBen();
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara']);
    $evaluation = PerformanceEvaluation::factory()->of($cara)->create();
    Goal::factory()->for($evaluation)->create(['title' => 'Lead the Checkout API']);

    actingAs($ben);

    visit(route('performance-evaluations.show', $evaluation))
        ->click('[aria-label="Progress of Lead the Checkout API"]')
        ->click('internal:role=option[name="Done"s]')
        ->assertSee('Saved')
        ->assertSee('Outcome (hidden from Cara until Completed)')
        ->assertSeeIn('[aria-label="Outcome of Lead the Checkout API"]', '—')
        ->click('[aria-label="Outcome of Lead the Checkout API"]')
        ->click('internal:role=option[name="Exceeded"s]')
        ->assertSee('Saved')
        ->assertSee('Pick an Outcome for every Goal and save the summary to Complete it.')
        ->fill('#evaluation-summary', "A strong year.\nThe Relaunch shipped on time.")
        ->click('internal:role=heading[name="Year as a whole"s]')
        ->assertSee('Pick an Outcome for every Goal and save the summary to Complete it.')
        ->click('Save summary')
        ->assertSee('Summary saved.')
        ->assertSee('Pick the Outcome of the year as a whole to Complete it.')
        ->click('#evaluation-outcome')
        ->click('internal:role=option[name="Met"s]')
        ->click('internal:role=button[name="Complete…"s]')
        ->assertSee('Complete Cara\'s 2026 Performance Evaluation as Met')
        ->assertSee('Cara sees the Outcomes and summary.')
        ->click('Keep it Open')
        ->assertSeeIn('#evaluation-outcome', 'Met')
        ->click('internal:role=button[name="Complete…"s]')
        ->click('internal:role=dialog >> internal:role=button[name="Complete Performance Evaluation"s]')
        ->assertSee('Performance Evaluation completed.')
        ->assertSee('Completed on')
        ->assertSee('A strong year.')
        ->assertSee('The Relaunch shipped on time.')
        ->assertMissing('#evaluation-outcome')
        ->assertNoJavaScriptErrors();

    actingAs($cara);

    visit(route('performance-evaluations.show', $evaluation))
        ->assertSee('Completed on')
        ->assertSeeIn('[data-slot="stat-strip"]', 'Met')
        ->assertSee('Outcome: Exceeded')
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
        ->click('internal:role=option[name="—"s]')
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
        ->assertSeeIn('#goal-outcome', '—')
        ->click('#goal-outcome')
        ->click('internal:role=option[name="Met"s]')
        ->assertSee('Saved')
        ->assertSee('The Goal was added')
        ->assertSeeIn('#goal-outcome', 'Met')
        ->assertNoJavaScriptErrors();

    expect($goal->fresh()?->outcome)->toBe(Outcome::Met);
});

it('completes an Evaluation from the Year as a whole panel on a phone, after confirming, without scrolling sideways', function () {
    $ben = managerBen();
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara']);
    $evaluation = PerformanceEvaluation::factory()->of($cara)->create(['summary' => 'A steady year.']);
    Goal::factory()->for($evaluation)->create(['title' => 'Lead the Checkout API', 'outcome' => Outcome::Met]);

    actingAs($ben);

    visit(route('performance-evaluations.show', $evaluation))
        ->resize(390, 844)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->click('#evaluation-outcome')
        ->click('internal:role=option[name="Partly Met"s]')
        ->click('internal:role=button[name="Complete…"s]')
        ->assertSee('Complete Cara\'s 2026 Performance Evaluation as Partly Met')
        ->click('internal:role=dialog >> internal:role=button[name="Complete Performance Evaluation"s]')
        ->assertSee('Performance Evaluation completed.')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();

    expect($evaluation->fresh()?->outcome)->toBe(Outcome::PartlyMet);
});
