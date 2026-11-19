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

it('creates a Report\'s Performance Evaluation, gives, changes and removes a Goal, and the Employee reads it', function () {
    $ben = managerBen();
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara']);

    actingAs($ben);

    visit(route('performance-evaluations.index'))
        ->click('internal:role=link[name="New Evaluation"s] >> nth=0')
        ->assertSee('New Performance Evaluation')
        ->assertSee("Only the people you're the Manager of who don't have a 2026 Evaluation yet.")
        ->click('2027')
        ->assertSee("Only the people you're the Manager of who don't have a 2027 Evaluation yet.")
        ->click('2026')
        ->click('#evaluation-employee')
        ->click('internal:role=option[name="Cara"s]')
        ->click('Create Evaluation')
        ->assertSee('Performance Evaluation created.')
        ->assertSee('The Goals you give for this year appear here.')
        ->click('internal:role=link[name="Give a Goal"s] >> nth=0')
        ->fill('#goal-title', 'Lead the Checkout API')
        ->fill('#goal-description', 'Own its design and delivery.')
        ->click('Give Goal')
        ->assertSee('Goal given.')
        ->assertSee('Lead the Checkout API')
        ->click('internal:role=link[name="Change Lead the Checkout API"s]')
        ->fill('#goal-title', 'Lead the Payments API')
        ->click('Save Goal')
        ->assertSee('Goal changed.')
        ->assertSee('Lead the Payments API')
        ->assertNoJavaScriptErrors();

    actingAs($cara);

    visit(route('my-performance-evaluations.index'))
        ->assertSee('2026')
        ->click('2026')
        ->assertSee('Lead the Payments API')
        ->assertDontSee('Give a Goal')
        ->assertNoJavaScriptErrors();

    actingAs($ben);

    visit(route('performance-evaluations.show', PerformanceEvaluation::query()->sole()))
        ->click('internal:role=button[name="Remove Lead the Payments API"s]')
        ->click('internal:role=dialog >> internal:role=button[name="Remove Goal"s]')
        ->assertSee('Goal removed.')
        ->assertNoJavaScriptErrors();

    expect(PerformanceEvaluation::query()->sole()->goals()->count())->toBe(0);
});
