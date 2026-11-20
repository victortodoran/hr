<?php

use App\Actions\AssessPerformanceEvaluation;
use App\Enums\Outcome;
use App\Enums\Progress;
use App\Http\Controllers\Performance\GoalController;
use App\Http\Controllers\Performance\GoalProgressController;
use App\Http\Controllers\Performance\PerformanceEvaluationController;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(PerformanceEvaluationController::class, GoalController::class, GoalProgressController::class);

beforeEach(fn () => travelTo('2026-12-01 09:00'));

it('opens a Goal from its Performance Evaluation, changes its Progress, and the Manager reads the history', function () {
    $ben = managerBen();
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara']);
    $evaluation = PerformanceEvaluation::factory()->of($cara)->create();
    $goal = Goal::factory()->for($evaluation)->create(['title' => 'Lead the Checkout API']);

    actingAs($cara);

    visit(route('performance-evaluations.show', $evaluation))
        ->assertSee('Not Started')
        ->click('internal:role=link[name="Lead the Checkout API"s]')
        ->assertSee('The Goal was added')
        ->click('#goal-progress')
        ->click('internal:role=option[name="At Risk"s]')
        ->assertSee('Saved')
        ->assertSee('Cara · from Not Started')
        ->assertNoJavaScriptErrors();

    actingAs($ben);

    visit(route('performance-evaluations.goals.show', [$evaluation, $goal]))
        ->assertSee('Cara · from Not Started')
        ->assertSee('At Risk')
        ->assertNoJavaScriptErrors();

    expect($goal->fresh()?->progress)->toBe(Progress::AtRisk);
});

it('changes a Goal\'s Progress from its card, kept in the Goal\'s history', function () {
    $ben = managerBen();
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara']);
    $evaluation = PerformanceEvaluation::factory()->of($cara)->create();
    $goal = Goal::factory()->for($evaluation)->create(['title' => 'Lead the Checkout API']);

    actingAs($cara);

    visit(route('performance-evaluations.show', $evaluation))
        ->click('[aria-label="Progress of Lead the Checkout API"]')
        ->click('internal:role=option[name="On Track"s]')
        ->assertSee('Saved')
        ->assertMissing('[aria-label="Outcome of Lead the Checkout API"]')
        ->click('internal:role=link[name="Lead the Checkout API"s]')
        ->assertSee('Cara · from Not Started')
        ->assertNoJavaScriptErrors();

    expect($goal->fresh()?->progress)->toBe(Progress::OnTrack);
});

it('puts the Progress back with a toast saying why when the Performance Evaluation was Completed meanwhile', function () {
    $ben = managerBen();
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara']);
    $evaluation = PerformanceEvaluation::factory()->of($cara)->create();
    $goal = Goal::factory()->for($evaluation)->create(['title' => 'Lead the Checkout API']);

    actingAs($cara);

    $page = visit(route('performance-evaluations.goals.show', [$evaluation, $goal]))
        ->assertSee('The Goal was added');

    $goal->forceFill(['outcome' => Outcome::Met])->save();
    app(AssessPerformanceEvaluation::class)->writeSummary($evaluation, 'Good year.');
    app(AssessPerformanceEvaluation::class)->complete($evaluation, Outcome::Met);

    $page->click('#goal-progress')
        ->click('internal:role=option[name="Done"s]')
        ->assertSee('The Progress wasn’t saved')
        ->assertMissing('#goal-progress')
        ->assertSee('Not Started')
        ->assertDontSee('Saved')
        ->assertNoJavaScriptErrors();

    expect($goal->fresh()?->progress)->toBe(Progress::NotStarted);
});

it('lets the Manager edit the Goal in the sheet on its page, then delete it from the ⋯ menu', function () {
    $ben = managerBen();
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara']);
    $evaluation = PerformanceEvaluation::factory()->of($cara)->create();
    $goal = Goal::factory()->for($evaluation)->create(['title' => 'Lead the Checkout API']);

    actingAs($ben);

    visit(route('performance-evaluations.goals.show', [$evaluation, $goal]))
        ->click('internal:role=link[name="Edit"s]')
        ->fill('#goal-title', 'Lead the Payments API')
        ->click('internal:role=dialog >> internal:role=button[name="Save Goal"s]')
        ->assertSee('Goal saved.')
        ->assertSee('The Goal was added')
        ->assertSee('Lead the Payments API')
        ->assertMissing('#goal-title')
        ->click('internal:role=button[name="More for this Goal"s]')
        ->click('internal:role=menuitem[name="Delete Goal…"s]')
        ->click('internal:role=dialog >> internal:role=button[name="Delete Goal"s]')
        ->assertSee('Goal deleted.')
        ->assertSee('No Goals yet')
        ->assertNoJavaScriptErrors();

    expect($evaluation->goals()->count())->toBe(0);
});
