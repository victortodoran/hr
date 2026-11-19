<?php

use App\Http\Controllers\Performance\GoalController;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(GoalController::class);

beforeEach(fn () => travelTo('2026-12-01 09:00'));

it('gives a Goal whose description keeps its line breaks', function () {
    $ben = managerBen();
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara Lungu']);
    $evaluation = PerformanceEvaluation::factory()->of($cara)->create();

    actingAs($ben);

    visit(route('performance-evaluations.goals.create', $evaluation))
        ->assertSee('Give Cara a Goal')
        ->assertSee('For the 2026 Performance Evaluation. Cara sees it as soon as you give it.')
        ->fill('#goal-title', 'Lead the Checkout API')
        ->fill('What achieving it looks like', "Own its design.\nShip it by October.")
        ->click('Give Goal')
        ->assertSee('Goal given.')
        ->assertNoJavaScriptErrors();

    expect($evaluation->goals()->sole()->description)->toBe("Own its design.\nShip it by October.");
});

it('changes a Goal, titled after it', function () {
    $ben = managerBen();
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara']);
    $evaluation = PerformanceEvaluation::factory()->of($cara)->create();
    $goal = Goal::factory()->for($evaluation)->create(['title' => 'Lead the Checkout API']);

    actingAs($ben);

    visit(route('performance-evaluations.goals.edit', [$evaluation, $goal]))
        ->assertSee('Change Lead the Checkout API')
        ->assertSee('For the 2026 Performance Evaluation.')
        ->assertDontSee('sees it as soon as you give it')
        ->assertNoJavaScriptErrors();
});
