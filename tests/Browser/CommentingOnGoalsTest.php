<?php

use App\Http\Controllers\Performance\GoalCommentController;
use App\Http\Controllers\Performance\GoalController;
use App\Http\Controllers\Performance\PerformanceEvaluationController;
use App\Models\Comment;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(PerformanceEvaluationController::class, GoalController::class, GoalCommentController::class);

beforeEach(fn () => travelTo('2026-12-01 09:00'));

it('lets the Manager and the Employee talk on a Goal, and warns how many Comments go when it is removed', function () {
    $ben = managerBen();
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara']);
    $evaluation = PerformanceEvaluation::factory()->of($cara)->create();
    $goal = Goal::factory()->for($evaluation)->create(['title' => 'Lead the Checkout API']);

    actingAs($ben);

    visit(route('performance-evaluations.goals.show', [$evaluation, $goal]))
        ->assertSee('What Cara and their Manager write about this Goal appears here.')
        ->fill('#goal-comment', 'Auth is late, let\'s talk Friday.')
        ->click('internal:role=button[name="Comment"s]')
        ->assertSee('Comment added.')
        ->assertSee('Auth is late, let\'s talk Friday.')
        ->assertNoJavaScriptErrors();

    actingAs($cara);

    visit(route('performance-evaluations.goals.show', [$evaluation, $goal]))
        ->assertSee('Auth is late, let\'s talk Friday.')
        ->fill('#goal-comment', 'Friday works.')
        ->click('internal:role=button[name="Comment"s]')
        ->assertSee('Friday works.')
        ->assertNoJavaScriptErrors();

    actingAs($ben);

    visit(route('performance-evaluations.show', $evaluation))
        ->click('internal:role=button[name="Remove Lead the Checkout API"s]')
        ->assertSee('Its 2 Comments are removed with it.')
        ->click('internal:role=dialog >> internal:role=button[name="Remove Goal"s]')
        ->assertSee('Goal removed.')
        ->assertNoJavaScriptErrors();

    expect(Comment::query()->count())->toBe(0);
});
