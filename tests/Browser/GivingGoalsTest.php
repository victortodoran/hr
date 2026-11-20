<?php

use App\Http\Controllers\Performance\GoalController;
use App\Http\Controllers\Performance\MyPerformanceEvaluationController;
use App\Http\Controllers\Performance\PerformanceEvaluationController;
use App\Models\Employee;
use App\Models\PerformanceEvaluation;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(PerformanceEvaluationController::class, GoalController::class, MyPerformanceEvaluationController::class);

beforeEach(fn () => travelTo('2026-12-01 09:00'));

it('creates a Report\'s Performance Evaluation in the dialog, adds, edits and deletes a Goal, and the Employee reads it', function () {
    $ben = managerBen();
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara']);

    actingAs($ben);

    visit(route('performance-evaluations.index'))
        ->click('internal:role=link[name="New Performance Evaluation…"s] >> nth=0')
        ->assertPathIs(route('performance-evaluations.create', absolute: false))
        ->assertNoJavaScriptErrors()
        ->assertSee("Only the people you're the Manager of who don't have a 2026 Performance Evaluation yet.")
        ->click('internal:role=dialog >> text=2027')
        ->assertSee("Only the people you're the Manager of who don't have a 2027 Performance Evaluation yet.")
        ->click('internal:role=dialog >> text=2026')
        ->click('#evaluation-employee')
        ->click('internal:role=option[name="Cara"s]')
        ->click('internal:role=dialog >> internal:role=button[name="New Performance Evaluation"s]')
        ->assertSee('Performance Evaluation created.')
        ->assertSee('The Goals you add for Cara for 2026 appear here.')
        ->click('internal:role=link[name="Add Goal"s]')
        ->fill('#goal-title', 'Lead the Checkout API')
        ->fill('#goal-description', 'Own its design and delivery.')
        ->click('internal:role=dialog >> internal:role=button[name="Add Goal"s]')
        ->assertSee('Goal added.')
        ->assertSee('Lead the Checkout API')
        ->click('internal:role=button[name="More for Lead the Checkout API"s]')
        ->click('internal:role=menuitem[name="Edit"s]')
        ->fill('#goal-title', 'Lead the Payments API')
        ->click('internal:role=dialog >> internal:role=button[name="Save Goal"s]')
        ->assertSee('Goal saved.')
        ->assertSee('Lead the Payments API')
        ->assertNoJavaScriptErrors();

    actingAs($cara);

    visit(route('my-performance-evaluations.index'))
        ->assertSee('2026')
        ->assertSee('Lead the Payments API')
        ->assertDontSee('Add Goal')
        ->assertNoJavaScriptErrors();

    actingAs($ben);

    visit(route('performance-evaluations.show', PerformanceEvaluation::query()->sole()))
        ->click('internal:role=button[name="More for Lead the Payments API"s]')
        ->click('internal:role=menuitem[name="Delete Goal…"s]')
        ->assertSee('Delete “Lead the Payments API”')
        ->click('internal:role=dialog >> internal:role=button[name="Delete Goal"s]')
        ->assertSee('Goal deleted.')
        ->assertNoJavaScriptErrors();

    expect(PerformanceEvaluation::query()->sole()->goals()->count())->toBe(0);
});
