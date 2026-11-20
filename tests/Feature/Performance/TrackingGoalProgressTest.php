<?php

use App\Actions\Performance\AssessPerformanceEvaluation;
use App\Actions\Performance\ChangeGoalProgress;
use App\Enums\Outcome;
use App\Enums\Permission;
use App\Enums\Progress;
use App\Enums\Scope;
use App\Http\Controllers\Performance\GoalController;
use App\Http\Controllers\Performance\GoalProgressController;
use App\Http\Controllers\Performance\PerformanceEvaluationController;
use App\Http\Requests\Performance\GoalProgressRequest;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;
use App\Models\ProgressChange;
use App\Policies\PerformanceEvaluationPolicy;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(ChangeGoalProgress::class, GoalController::class, GoalProgressController::class, GoalProgressRequest::class, PerformanceEvaluationController::class, PerformanceEvaluationPolicy::class, Goal::class, ProgressChange::class, Progress::class);

beforeEach(fn () => travelTo('2026-12-01 09:00'));

/**
 * Ana, Ben, Cara (each the Manager of the next) and the Goal on Cara's Open 2026 Performance Evaluation, given on 1 March.
 *
 * @return array{Employee, Employee, Employee, Goal}
 */
function goalProgressLine(): array
{
    $ana = Employee::factory()->startedLongAgo()->create(['name' => 'Ana']);
    $ben = Employee::factory()->startedLongAgo()->reportingTo($ana)->create(['name' => 'Ben']);
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara']);
    $evaluation = PerformanceEvaluation::factory()->of($cara)->create();
    $goal = Goal::factory()->for($evaluation)->create([
        'title' => 'Lead the Checkout API',
        'description' => 'Own its design.',
        'created_at' => '2026-03-01 10:00',
    ]);

    return [$ana, $ben, $cara, $goal];
}

function progressRoute(Goal $goal): string
{
    return route('performance-evaluations.goals.progress.update', [$goal->performanceEvaluation, $goal]);
}

function goalRoute(Goal $goal): string
{
    return route('performance-evaluations.goals.show', [$goal->performanceEvaluation, $goal]);
}

it('starts every Goal Not Started, with no history', function () {
    [, , $cara, $goal] = goalProgressLine();

    actingAs($cara)->get(goalRoute($goal))
        ->assertInertia(fn (Assert $page) => $page
            ->component('performance-evaluations/goals/Show')
            ->where('goal', [
                'id' => $goal->id,
                'title' => 'Lead the Checkout API',
                'description' => 'Own its design.',
                'progress' => 'not-started',
                'outcome' => null,
                'given_at' => '2026-03-01',
            ])
            ->where('evaluation', ['id' => $goal->performance_evaluation_id, 'year' => 2026, 'employee' => ['id' => $cara->id, 'name' => 'Cara']])
            ->where('history', [])
            ->where('isOwn', true)
            ->where('canChangeProgress', true));
});

it('lets the Employee and their effective Manager change a Goal\'s Progress, keeping every change newest first', function () {
    [, $ben, $cara, $goal] = goalProgressLine();

    travelTo('2026-11-02 08:30');
    actingAs($cara)->put(progressRoute($goal), ['progress' => 'on-track'])
        ->assertRedirect(goalRoute($goal));
    travelTo('2026-11-20 16:15');
    actingAs($ben)->put(progressRoute($goal), ['progress' => 'at-risk'])
        ->assertRedirect(goalRoute($goal));

    expect($goal->fresh()?->progress)->toBe(Progress::AtRisk);
    actingAs($ben)->get(goalRoute($goal))
        ->assertInertia(fn (Assert $page) => $page
            ->where('goal.progress', 'at-risk')
            ->where('history', [
                ['progress' => 'at-risk', 'previous_progress' => 'on-track', 'changed_by' => 'Ben', 'changed_at' => '2026-11-20T16:15:00+00:00'],
                ['progress' => 'on-track', 'previous_progress' => 'not-started', 'changed_by' => 'Cara', 'changed_at' => '2026-11-02T08:30:00+00:00'],
            ])
            ->where('isOwn', false)
            ->where('canChangeProgress', true));
});

it('lets the Employee and their Manager change a Goal\'s Progress from its card, leading back to the Performance Evaluation\'s page', function () {
    [, $ben, $cara, $goal] = goalProgressLine();
    $evaluationPage = route('performance-evaluations.show', $goal->performanceEvaluation);

    foreach ([$cara, $ben] as $actor) {
        actingAs($actor)->get($evaluationPage)
            ->assertInertia(fn (Assert $page) => $page->where('canChangeProgress', true));
    }

    actingAs($cara)->from($evaluationPage)->put(progressRoute($goal), ['progress' => 'at-risk'])
        ->assertRedirect($evaluationPage);

    expect($goal->fresh()?->progress)->toBe(Progress::AtRisk)
        ->and($goal->progressChanges()->sole()->changed_by_id)->toBe($cara->id);
});

it('keeps no change when the Progress stays the same', function () {
    [, , $cara, $goal] = goalProgressLine();

    actingAs($cara)->put(progressRoute($goal), ['progress' => 'not-started'])->assertRedirect(goalRoute($goal));

    expect($goal->progressChanges()->count())->toBe(0);
});

it('refuses a Progress outside the fixed list', function () {
    [, , $cara, $goal] = goalProgressLine();

    actingAs($cara)->put(progressRoute($goal), ['progress' => 'half-done'])->assertSessionHasErrors('progress');
    actingAs($cara)->put(progressRoute($goal), [])->assertSessionHasErrors('progress');

    expect($goal->fresh()?->progress)->toBe(Progress::NotStarted);
});

it('lets readers of the Performance Evaluation see the Progress but not change it', function (Closure $reader) {
    [$ana, $ben, $cara, $goal] = goalProgressLine();
    $actor = $reader($ana);

    actingAs($actor)->get(goalRoute($goal))
        ->assertInertia(fn (Assert $page) => $page
            ->where('goal.progress', 'not-started')
            ->where('canChangeProgress', false));
    actingAs($actor)->get(route('performance-evaluations.show', $goal->performanceEvaluation))
        ->assertInertia(fn (Assert $page) => $page->where('canChangeProgress', false));
    actingAs($actor)->put(progressRoute($goal), ['progress' => 'done'])->assertForbidden();

    expect($goal->fresh()?->progress)->toBe(Progress::NotStarted);
})->with([
    'the Manager\'s Manager, reading through a Role' => [function (Employee $ana): Employee {
        giveRole($ana, [Permission::ViewPerformanceEvaluations->value => Scope::Everyone]);

        return $ana;
    }],
    'HR with view performance evaluations' => [fn (): Employee => employeeWith([Permission::ViewPerformanceEvaluations->value => Scope::Everyone], ['name' => 'Eve'])],
]);

it('refuses the Goal\'s page and its Progress to anyone who can\'t see the Performance Evaluation', function () {
    [$ana, , , $goal] = goalProgressLine();

    foreach ([$ana, Employee::factory()->create()] as $outsider) {
        actingAs($outsider)->get(goalRoute($goal))->assertForbidden();
        actingAs($outsider)->put(progressRoute($goal), ['progress' => 'done'])->assertForbidden();
    }
});

it('refuses a Goal through another Performance Evaluation', function () {
    [, , $cara, $goal] = goalProgressLine();
    $other = PerformanceEvaluation::factory()->of($cara)->create(['year' => 2025]);

    actingAs($cara)->get(route('performance-evaluations.goals.show', [$other, $goal]))->assertNotFound();
    actingAs($cara)->put(route('performance-evaluations.goals.progress.update', [$other, $goal]), ['progress' => 'done'])->assertNotFound();
});

it('refuses changing Progress once the Performance Evaluation is Completed, and allows it again after a Reopen', function () {
    [, $ben, $cara, $goal] = goalProgressLine();
    $evaluation = $goal->performanceEvaluation;
    $assess = app(AssessPerformanceEvaluation::class);
    $goal->forceFill(['outcome' => Outcome::Met])->save();
    $assess->writeSummary($evaluation, 'Good year.');
    $assess->complete($evaluation, Outcome::Met);

    actingAs($cara)->get(goalRoute($goal))->assertInertia(fn (Assert $page) => $page->where('canChangeProgress', false));
    actingAs($cara)->put(progressRoute($goal), ['progress' => 'done'])->assertForbidden();
    actingAs($ben)->put(progressRoute($goal), ['progress' => 'done'])->assertForbidden();

    $assess->reopen($evaluation->fresh() ?? $evaluation);

    actingAs($cara)->put(progressRoute($goal), ['progress' => 'done'])->assertRedirect(goalRoute($goal));
    expect($goal->fresh()?->progress)->toBe(Progress::Done);
});

it('completes a Performance Evaluation whatever its Goals\' Progress', function () {
    [, $ben, , $goal] = goalProgressLine();
    actingAs($ben)->put(progressRoute($goal), ['progress' => 'at-risk']);

    $goal->forceFill(['outcome' => Outcome::NotMet])->save();
    $goal->performanceEvaluation->forceFill(['summary' => 'The Checkout API slipped.'])->save();

    actingAs($ben)->post(route('performance-evaluations.completion.store', $goal->performanceEvaluation), ['outcome' => 'partly-met']);

    expect($goal->performanceEvaluation->fresh()?->isCompleted())->toBeTrue();
});

it('shows each Goal\'s Progress on the Performance Evaluation\'s page, to the Employee as well', function () {
    [, $ben, $cara, $goal] = goalProgressLine();
    actingAs($ben)->put(progressRoute($goal), ['progress' => 'at-risk']);

    actingAs($cara)->get(route('performance-evaluations.show', $goal->performanceEvaluation))
        ->assertInertia(fn (Assert $page) => $page
            ->where('evaluation.goals', fn (Collection $goals): bool => $goals->pluck('progress')->all() === ['at-risk']));
});

it('lets the next Manager up change Progress once the Employee\'s Manager has left', function () {
    [$ana, $ben, , $goal] = goalProgressLine();
    $ben->update(['leaving_date' => '2026-10-31']);

    actingAs($ana)->put(progressRoute($goal), ['progress' => 'on-track'])->assertRedirect(goalRoute($goal));

    expect($goal->fresh()?->progress)->toBe(Progress::OnTrack);
});

it('offers Change and Remove on the Goal\'s page only to the effective Manager while it is Open', function () {
    [$ana, $ben, $cara, $goal] = goalProgressLine();
    giveRole($ana, [Permission::ViewPerformanceEvaluations->value => Scope::Everyone]);

    actingAs($ben)->get(goalRoute($goal))->assertInertia(fn (Assert $page) => $page->where('canEdit', true));
    actingAs($cara)->get(goalRoute($goal))->assertInertia(fn (Assert $page) => $page->where('canEdit', false));
    actingAs($ana)->get(goalRoute($goal))->assertInertia(fn (Assert $page) => $page->where('canEdit', false));

    $goal->forceFill(['outcome' => Outcome::Met])->save();
    app(AssessPerformanceEvaluation::class)->writeSummary($goal->performanceEvaluation, 'Good year.');
    app(AssessPerformanceEvaluation::class)->complete($goal->performanceEvaluation, Outcome::Met);

    actingAs($ben)->get(goalRoute($goal))->assertInertia(fn (Assert $page) => $page->where('canEdit', false));
});

it('opens the Goal\'s page with its fields showing when asked, only for whoever may change it', function () {
    [, $ben, $cara, $goal] = goalProgressLine();
    $editing = route('performance-evaluations.goals.show', [$goal->performanceEvaluation, $goal, 'editing' => '1']);

    actingAs($ben)->get($editing)->assertInertia(fn (Assert $page) => $page->where('startsEditing', true));
    actingAs($ben)->get(goalRoute($goal))->assertInertia(fn (Assert $page) => $page->where('startsEditing', false));
    actingAs($cara)->get($editing)->assertInertia(fn (Assert $page) => $page->where('startsEditing', false));
});

it('changes the Goal on its own page and leads back there, with no separate page for changing it', function () {
    [, $ben, , $goal] = goalProgressLine();

    actingAs($ben)->get(route('performance-evaluations.goals.show', [$goal->performanceEvaluation, $goal]).'/edit')->assertNotFound();

    actingAs($ben)->put(route('performance-evaluations.goals.update', [$goal->performanceEvaluation, $goal]), ['title' => 'Lead the Payments API', 'description' => 'Own it.'])
        ->assertRedirect(goalRoute($goal));
    expect($goal->fresh()?->only('title', 'description'))->toBe(['title' => 'Lead the Payments API', 'description' => 'Own it.']);
});
