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

it('changes a Goal\'s Progress on a phone without scrolling sideways', function () {
    $cara = Employee::factory()->startedLongAgo()->reportingTo(managerBen())->create(['name' => 'Cara']);
    PerformanceEvaluation::factory()->of($cara)->completed()->create(['year' => 2025]);
    $goal = Goal::factory()->for(PerformanceEvaluation::factory()->of($cara))->create(['title' => 'Lead the Checkout API for every European market']);

    actingAs($cara);

    visit(route('my-performance-evaluations.index'))
        ->resize(390, 844)
        ->assertVisible('[aria-label="Progress of Lead the Checkout API for every European market"]')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->click('[aria-label="Progress of Lead the Checkout API for every European market"]')
        ->click('internal:role=option[name="At Risk"s]')
        ->assertSee('Saved')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();

    expect($goal->fresh()?->progress)->toBe(Progress::AtRisk);
});

it('says the Manager hasn\'t set this year\'s Goals yet while there is no Evaluation for it', function () {
    $cara = Employee::factory()->startedLongAgo()->reportingTo(managerBen())->create(['name' => 'Cara']);

    actingAs($cara);

    visit(route('my-performance-evaluations.index'))
        ->assertSee("Your Manager hasn't added your 2026 Goals yet.")
        ->assertSee('Your Performance Evaluations for other years appear here.')
        ->assertNoJavaScriptErrors();
});

it('shows the years as tabs once there are more than three, each other year with its facts and a link to it', function () {
    $cara = Employee::factory()->startedLongAgo()->reportingTo(managerBen())->create(['name' => 'Cara']);
    Goal::factory()->for(PerformanceEvaluation::factory()->of($cara))->create(['title' => 'Lead the Checkout API']);
    $earlier = PerformanceEvaluation::factory()->of($cara)->completed()->create(['year' => 2024]);
    PerformanceEvaluation::factory()->of($cara)->completed()->create(['year' => 2025]);
    PerformanceEvaluation::factory()->of($cara)->completed()->create(['year' => 2023]);

    actingAs($cara);

    visit(route('my-performance-evaluations.index'))
        ->assertSee('Lead the Checkout API')
        ->assertDontSee('Past years')
        ->click('internal:role=tab[name="2024"s]')
        ->assertDontSee('Lead the Checkout API')
        ->assertSee('2024 Performance Evaluation')
        ->click('internal:role=link[name="2024 Performance Evaluation"s]')
        ->assertPathIs(route('performance-evaluations.show', $earlier, false))
        ->assertNoJavaScriptErrors();
});
