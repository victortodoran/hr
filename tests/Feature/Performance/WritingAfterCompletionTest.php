<?php

use App\Actions\AssessPerformanceEvaluation;
use App\Actions\ChangeGoal;
use App\Actions\ChangeGoalProgress;
use App\Actions\CommentOnGoal;
use App\Actions\GiveGoal;
use App\Actions\RemoveGoal;
use App\Enums\Outcome;
use App\Enums\PerformanceEvaluationState;
use App\Enums\Progress;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;
use Illuminate\Auth\Access\AuthorizationException;

use function Pest\Laravel\travelTo;

covers(AssessPerformanceEvaluation::class, GiveGoal::class, ChangeGoal::class, RemoveGoal::class, ChangeGoalProgress::class, CommentOnGoal::class);

beforeEach(fn () => travelTo('2026-12-01 09:00'));

/**
 * Cara's Open 2026 Performance Evaluation with one Goal, Met, and Ben,
 * her Manager, holding it as loaded while it was still Open.
 *
 * @return array{Employee, Employee, PerformanceEvaluation, Goal}
 */
function evaluationLoadedWhileOpen(): array
{
    $ben = managerBen();
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara']);
    $evaluation = PerformanceEvaluation::factory()->of($cara)->create(['outcome' => Outcome::Met, 'summary' => 'Good year.']);
    $goal = Goal::factory()->for($evaluation)->create(['title' => 'Lead the Checkout API', 'outcome' => Outcome::Met]);

    return [$ben, $cara, $evaluation, $goal];
}

/**
 * Complete the Performance Evaluation in the database, leaving the
 * models already loaded thinking it is still Open, as a Complete made
 * between the policy check and the write would.
 */
function completeSinceLoaded(PerformanceEvaluation $evaluation): void
{
    PerformanceEvaluation::query()->whereKey($evaluation->id)->update(['state' => PerformanceEvaluationState::Completed, 'completed_at' => now()->subMinute()]);
}

it('saves no assessment on an Evaluation Completed since it was loaded', function () {
    [, , $evaluation, $goal] = evaluationLoadedWhileOpen();
    completeSinceLoaded($evaluation);

    $saved = app(AssessPerformanceEvaluation::class)->save($evaluation, [$goal->id => Outcome::NotMet], Outcome::NotMet, 'Rewritten.');

    expect($saved)->toBeFalse()
        ->and($goal->fresh()?->outcome)->toBe(Outcome::Met)
        ->and($evaluation->fresh()?->outcome)->toBe(Outcome::Met)
        ->and($evaluation->fresh()?->summary)->toBe('Good year.');
});

it('completes no Evaluation again once Completed since it was loaded, keeping the first Completion', function () {
    [, , $evaluation, $goal] = evaluationLoadedWhileOpen();
    completeSinceLoaded($evaluation);

    $completed = app(AssessPerformanceEvaluation::class)->complete($evaluation, [$goal->id => Outcome::NotMet], Outcome::NotMet, 'Rewritten.');

    expect($completed)->toBeFalse()
        ->and($goal->fresh()?->outcome)->toBe(Outcome::Met)
        ->and($evaluation->fresh()?->summary)->toBe('Good year.')
        ->and($evaluation->fresh()?->completed_at?->toDateTimeString())->toBe('2026-12-01 08:59:00');
});

it('gives no Goal on an Evaluation Completed since it was loaded', function () {
    [, , $evaluation] = evaluationLoadedWhileOpen();
    completeSinceLoaded($evaluation);

    $given = app(GiveGoal::class)->give($evaluation, ['title' => 'Mentor Lia', 'description' => 'Weekly.']);

    expect($given)->toBeFalse()
        ->and($evaluation->goals()->pluck('title')->all())->toBe(['Lead the Checkout API']);
});

it('changes no Goal on an Evaluation Completed since it was loaded', function () {
    [, , $evaluation, $goal] = evaluationLoadedWhileOpen();
    completeSinceLoaded($evaluation);

    $changed = app(ChangeGoal::class)->change($goal, ['title' => 'Rewritten', 'description' => 'Rewritten.']);

    expect($changed)->toBeFalse()
        ->and($goal->fresh()?->title)->toBe('Lead the Checkout API');
});

it('removes no Goal from an Evaluation Completed since it was loaded', function () {
    [, , $evaluation, $goal] = evaluationLoadedWhileOpen();
    completeSinceLoaded($evaluation);

    $removed = app(RemoveGoal::class)->remove($goal);

    expect($removed)->toBeFalse()
        ->and($goal->fresh())->not->toBeNull();
});

it('changes no Progress on an Evaluation Completed since it was loaded', function () {
    [, $cara, $evaluation, $goal] = evaluationLoadedWhileOpen();
    completeSinceLoaded($evaluation);

    expect(fn () => app(ChangeGoalProgress::class)->change($goal, Progress::AtRisk, $cara))->toThrow(AuthorizationException::class)
        ->and($goal->fresh()?->progress)->toBe(Progress::NotStarted)
        ->and($goal->progressChanges()->count())->toBe(0);
});

it('writes no Comment on an Evaluation Completed since it was loaded', function () {
    [, $cara, $evaluation, $goal] = evaluationLoadedWhileOpen();
    completeSinceLoaded($evaluation);

    $written = app(CommentOnGoal::class)->comment($goal, $cara, 'Too late?');

    expect($written)->toBeFalse()
        ->and($goal->comments()->count())->toBe(0);
});
