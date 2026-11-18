<?php

use App\Actions\AssessPerformanceEvaluation;
use App\Actions\ChangeGoalProgress;
use App\Enums\Outcome;
use App\Enums\Permission;
use App\Enums\Progress;
use App\Enums\Scope;
use App\Http\Controllers\Performance\GoalCommentController;
use App\Http\Controllers\Performance\GoalController;
use App\Http\Controllers\Performance\PerformanceEvaluationController;
use App\Http\Requests\Performance\CommentRequest;
use App\Models\Comment;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;
use App\Models\ProgressChange;
use App\Policies\PerformanceEvaluationPolicy;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(GoalCommentController::class, GoalController::class, CommentRequest::class, PerformanceEvaluationController::class, PerformanceEvaluationPolicy::class, Comment::class, Goal::class);

beforeEach(fn () => travelTo('2026-12-01 09:00'));

/**
 * Ana, who manages Ben, who manages Cara, and Cara's Open 2026
 * Performance Evaluation with one Goal.
 *
 * @return array{Employee, Employee, Employee, Goal}
 */
function goalCommentLine(): array
{
    $ana = Employee::factory()->startedLongAgo()->create(['name' => 'Ana']);
    $ben = Employee::factory()->startedLongAgo()->reportingTo($ana)->create(['name' => 'Ben']);
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara']);
    $goal = Goal::factory()->for(PerformanceEvaluation::factory()->of($cara))->create(['title' => 'Lead the Checkout API']);

    return [$ana, $ben, $cara, $goal];
}

/**
 * The route that writes a Comment on the Goal.
 */
function commentRoute(Goal $goal): string
{
    return route('performance-evaluations.goals.comments.store', [$goal->performanceEvaluation, $goal]);
}

/**
 * The route of the Goal's page.
 */
function commentedGoalRoute(Goal $goal): string
{
    return route('performance-evaluations.goals.show', [$goal->performanceEvaluation, $goal]);
}

it('lets the Employee and their effective Manager write Comments, shown oldest first with author and time', function () {
    [, $ben, $cara, $goal] = goalCommentLine();

    travelTo('2026-11-02 08:30');
    actingAs($ben)->post(commentRoute($goal), ['body' => '  Auth is late, let\'s talk Friday.  '])
        ->assertRedirect(commentedGoalRoute($goal));
    travelTo('2026-11-03 10:00');
    actingAs($cara)->post(commentRoute($goal), ['body' => 'Friday works.'])
        ->assertRedirect(commentedGoalRoute($goal));

    actingAs($cara)->get(commentedGoalRoute($goal))
        ->assertInertia(fn (Assert $page) => $page
            ->where('comments', fn (Collection $comments): bool => $comments->map(fn (array $comment): array => collect($comment)->except('id')->all())->all() === [
                ['body' => 'Auth is late, let\'s talk Friday.', 'author' => 'Ben', 'written_at' => '2026-11-02T08:30:00+00:00'],
                ['body' => 'Friday works.', 'author' => 'Cara', 'written_at' => '2026-11-03T10:00:00+00:00'],
            ])
            ->where('canComment', true));
});

it('requires a Comment\'s text', function () {
    [, , $cara, $goal] = goalCommentLine();

    actingAs($cara)->post(commentRoute($goal), ['body' => '   '])->assertSessionHasErrors('body');

    expect($goal->comments()->count())->toBe(0);
});

it('lets readers of the Performance Evaluation read the Comments but not write them', function (Closure $reader) {
    [$ana, $ben, , $goal] = goalCommentLine();
    actingAs($ben)->post(commentRoute($goal), ['body' => 'Auth is late.']);
    $actor = $reader($ana);

    actingAs($actor)->get(commentedGoalRoute($goal))
        ->assertInertia(fn (Assert $page) => $page
            ->where('comments.0.body', 'Auth is late.')
            ->where('canComment', false));
    actingAs($actor)->post(commentRoute($goal), ['body' => 'From above.'])->assertForbidden();

    expect($goal->comments()->count())->toBe(1);
})->with([
    'the Manager\'s Manager, reading through a Role' => [function (Employee $ana): Employee {
        giveRole($ana, [Permission::ViewPerformanceEvaluations->value => Scope::Everyone]);

        return $ana;
    }],
    'HR with view performance evaluations' => [fn (): Employee => employeeWith([Permission::ViewPerformanceEvaluations->value => Scope::Everyone], ['name' => 'Eve'])],
]);

it('refuses Comments to anyone who can\'t see the Performance Evaluation, and through another Evaluation', function () {
    [$ana, , $cara, $goal] = goalCommentLine();
    $other = PerformanceEvaluation::factory()->of($cara)->create(['year' => 2025]);

    actingAs($ana)->post(commentRoute($goal), ['body' => 'Hello'])->assertForbidden();
    actingAs($cara)->post(route('performance-evaluations.goals.comments.store', [$other, $goal]), ['body' => 'Hello'])->assertNotFound();

    expect($goal->comments()->count())->toBe(0);
});

it('refuses Comments once the Performance Evaluation is Completed, and allows them again after a Reopen', function () {
    [, $ben, $cara, $goal] = goalCommentLine();
    $assess = app(AssessPerformanceEvaluation::class);
    $assess->complete($goal->performanceEvaluation, [$goal->id => Outcome::Met], Outcome::Met, 'Good year.');

    actingAs($cara)->get(commentedGoalRoute($goal))->assertInertia(fn (Assert $page) => $page->where('canComment', false));
    actingAs($cara)->post(commentRoute($goal), ['body' => 'Late note'])->assertForbidden();
    actingAs($ben)->post(commentRoute($goal), ['body' => 'Late note'])->assertForbidden();

    $assess->reopen($goal->performanceEvaluation->fresh() ?? $goal->performanceEvaluation);

    actingAs($ben)->post(commentRoute($goal), ['body' => 'Reopened to fix the Outcome.'])->assertRedirect(commentedGoalRoute($goal));
    expect($goal->comments()->count())->toBe(1);
});

it('keeps a Comment\'s author after they stop being the Manager, and lets the new Manager join the thread', function () {
    [, $ben, $cara, $goal] = goalCommentLine();
    actingAs($ben)->post(commentRoute($goal), ['body' => 'Handing you over.']);
    $hana = Employee::factory()->startedLongAgo()->create(['name' => 'Hana']);
    $cara->update(['manager_id' => $hana->id]);

    actingAs($ben)->post(commentRoute($goal), ['body' => 'One more thing.'])->assertForbidden();
    actingAs($hana)->post(commentRoute($goal), ['body' => 'Hi Cara.'])->assertRedirect(commentedGoalRoute($goal));

    actingAs($cara)->get(commentedGoalRoute($goal))
        ->assertInertia(fn (Assert $page) => $page
            ->where('comments', fn (Collection $comments): bool => $comments->pluck('author')->all() === ['Ben', 'Hana']));
});

it('offers no way to change or remove a Comment', function () {
    expect(collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_contains($route->uri(), 'comments'))
        ->flatMap(fn ($route): array => $route->methods())
        ->unique()->values()->all())->toBe(['POST']);
});

it('counts each Goal\'s Comments on the Performance Evaluation\'s page, for the warning before removing it', function () {
    [, $ben, $cara, $goal] = goalCommentLine();
    actingAs($ben)->post(commentRoute($goal), ['body' => 'One']);
    actingAs($cara)->post(commentRoute($goal), ['body' => 'Two']);

    actingAs($ben)->get(route('performance-evaluations.show', $goal->performanceEvaluation))
        ->assertInertia(fn (Assert $page) => $page->where('evaluation.goals.0.comments_count', 2));
});

it('removes a Goal\'s Comments and Progress history with it', function () {
    [, $ben, $cara, $goal] = goalCommentLine();
    actingAs($cara)->post(commentRoute($goal), ['body' => 'Started.']);
    app(ChangeGoalProgress::class)->change($goal, Progress::OnTrack, $cara);

    actingAs($ben)->delete(route('performance-evaluations.goals.destroy', [$goal->performanceEvaluation, $goal]))->assertRedirect();

    expect(Comment::query()->count())->toBe(0)
        ->and(ProgressChange::query()->count())->toBe(0);
});
