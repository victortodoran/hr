<?php

use App\Actions\AssessPerformanceEvaluation;
use App\Enums\Outcome;
use App\Enums\PerformanceEvaluationState;
use App\Http\Controllers\Performance\GoalOutcomeController;
use App\Http\Controllers\Performance\MyPerformanceEvaluationController;
use App\Http\Controllers\Performance\PerformanceEvaluationAssessmentController;
use App\Http\Controllers\Performance\PerformanceEvaluationCompletionController;
use App\Http\Controllers\Performance\PerformanceEvaluationController;
use App\Http\Requests\Performance\AssessmentRequest;
use App\Http\Requests\Performance\GoalOutcomeRequest;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;
use App\Policies\PerformanceEvaluationPolicy;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(GoalOutcomeController::class, GoalOutcomeRequest::class, PerformanceEvaluationAssessmentController::class, PerformanceEvaluationCompletionController::class, AssessPerformanceEvaluation::class, AssessmentRequest::class, PerformanceEvaluationController::class, MyPerformanceEvaluationController::class, PerformanceEvaluationPolicy::class);

beforeEach(fn () => travelTo('2026-12-01 09:00'));

/**
 * Cara's Open 2026 Performance Evaluation with two Goals, written by Ben,
 * her Manager.
 *
 * @return array{Employee, Employee, PerformanceEvaluation, Goal, Goal}
 */
function carasEvaluation(): array
{
    $ben = managerBen();
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara']);
    $evaluation = PerformanceEvaluation::factory()->of($cara)->create();
    $api = Goal::factory()->for($evaluation)->create(['title' => 'Lead the Checkout API']);
    $mentoring = Goal::factory()->for($evaluation)->create(['title' => 'Mentor Lia']);

    return [$ben, $cara, $evaluation, $api, $mentoring];
}

/**
 * The year card's form as it is sent: the whole's Outcome and the summary.
 *
 * @return array<string, mixed>
 */
function assessmentForm(?string $outcome, ?string $summary): array
{
    return ['outcome' => $outcome, 'summary' => $summary];
}

/**
 * The route that gives one Goal its Outcome.
 */
function goalOutcomeRoute(Goal $goal): string
{
    return route('performance-evaluations.goals.outcome.update', [$goal->performance_evaluation_id, $goal]);
}

it('lets the Manager give, change and clear one Goal\'s Outcome, leaving the rest as they are', function () {
    [$ben, , $evaluation, $api, $mentoring] = carasEvaluation();
    $mentoring->forceFill(['outcome' => Outcome::Exceeded])->save();

    actingAs($ben)->from(route('performance-evaluations.show', $evaluation))->put(goalOutcomeRoute($api), ['outcome' => 'met'])
        ->assertRedirect(route('performance-evaluations.show', $evaluation))
        ->assertSessionHasNoErrors();
    expect($api->refresh()->outcome)->toBe(Outcome::Met)
        ->and($mentoring->refresh()->outcome)->toBe(Outcome::Exceeded);

    actingAs($ben)->put(goalOutcomeRoute($api), ['outcome' => 'partly-met'])->assertRedirect();
    expect($api->refresh()->outcome)->toBe(Outcome::PartlyMet);

    actingAs($ben)->put(goalOutcomeRoute($api), ['outcome' => null])->assertRedirect();
    expect($api->refresh()->outcome)->toBeNull()
        ->and($mentoring->refresh()->outcome)->toBe(Outcome::Exceeded)
        ->and($evaluation->refresh()->state)->toBe(PerformanceEvaluationState::Open);
});

it('lets the Manager save the whole\'s Outcome and summary bit by bit, leaving the Goals\' Outcomes as they are', function () {
    [$ben, , $evaluation, $api] = carasEvaluation();
    $api->forceFill(['outcome' => Outcome::Met])->save();

    actingAs($ben)->put(route('performance-evaluations.assessment.update', $evaluation), assessmentForm(null, ' Strong start. '))
        ->assertRedirect(route('performance-evaluations.show', $evaluation))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Outcomes saved.']);

    expect($evaluation->refresh()->summary)->toBe('Strong start.')
        ->and($evaluation->outcome)->toBeNull()
        ->and($evaluation->state)->toBe(PerformanceEvaluationState::Open)
        ->and($api->refresh()->outcome)->toBe(Outcome::Met);

    actingAs($ben)->put(route('performance-evaluations.assessment.update', $evaluation), [...assessmentForm('met', ''), 'goals' => [['id' => $api->id, 'outcome' => null]]])->assertRedirect();

    expect($evaluation->refresh()->outcome)->toBe(Outcome::Met)
        ->and($evaluation->summary)->toBeNull()
        ->and($api->refresh()->outcome)->toBe(Outcome::Met);
});

it('lets the Manager Complete the Evaluation once every Goal and the whole have an Outcome and there is a summary', function () {
    [$ben, , $evaluation, $api, $mentoring] = carasEvaluation();
    actingAs($ben)->put(goalOutcomeRoute($api), ['outcome' => 'met']);
    actingAs($ben)->put(goalOutcomeRoute($mentoring), ['outcome' => 'partly-met']);

    actingAs($ben)->post(route('performance-evaluations.completion.store', $evaluation), assessmentForm('met', 'A good year.'))
        ->assertRedirect(route('performance-evaluations.show', $evaluation))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Performance Evaluation completed.']);

    $evaluation->refresh();
    expect($evaluation->state)->toBe(PerformanceEvaluationState::Completed)
        ->and($evaluation->completed_at)->toEqual(now())
        ->and($evaluation->outcome)->toBe(Outcome::Met)
        ->and($evaluation->summary)->toBe('A good year.')
        ->and($api->refresh()->outcome)->toBe(Outcome::Met)
        ->and($mentoring->refresh()->outcome)->toBe(Outcome::PartlyMet);
});

it('refuses Completing, saving nothing, while a Goal or the whole lacks an Outcome or the summary is empty', function (?string $mentoringOutcome, array $form) {
    [$ben, , $evaluation, $api, $mentoring] = carasEvaluation();
    $api->forceFill(['outcome' => Outcome::Met])->save();
    $mentoring->forceFill(['outcome' => $mentoringOutcome])->save();

    actingAs($ben)->post(route('performance-evaluations.completion.store', $evaluation), $form)
        ->assertSessionHasErrors(['outcome' => 'Give every Goal and the whole an Outcome, and write a summary, before completing.']);

    expect($evaluation->refresh()->state)->toBe(PerformanceEvaluationState::Open)
        ->and($evaluation->summary)->toBeNull()
        ->and($evaluation->outcome)->toBeNull();
})->with([
    'a Goal without an Outcome' => [null, assessmentForm('met', 'A good year.')],
    'no Outcome for the whole' => ['met', assessmentForm(null, 'A good year.')],
    'no summary' => ['met', assessmentForm('met', '  ')],
]);

it('refuses Completing an Evaluation with no Goals', function () {
    $ben = managerBen();
    $evaluation = PerformanceEvaluation::factory()->of(Employee::factory()->startedLongAgo()->reportingTo($ben)->create())->create();

    actingAs($ben)->post(route('performance-evaluations.completion.store', $evaluation), assessmentForm('met', 'A good year.'))
        ->assertSessionHasErrors(['outcome' => 'Give at least one Goal before completing.']);
    expect($evaluation->refresh()->state)->toBe(PerformanceEvaluationState::Open);
});

it('refuses an Outcome not on the list, and a Goal from another Evaluation', function () {
    [$ben, , $evaluation, $api] = carasEvaluation();
    $elsewhere = Goal::factory()->create();

    actingAs($ben)->put(route('performance-evaluations.assessment.update', $evaluation), assessmentForm('superb', null))
        ->assertSessionHasErrors('outcome');
    actingAs($ben)->put(goalOutcomeRoute($api), ['outcome' => 'brilliant'])->assertSessionHasErrors('outcome');
    actingAs($ben)->put(goalOutcomeRoute($api), [])->assertSessionHasErrors('outcome');
    actingAs($ben)->put(route('performance-evaluations.goals.outcome.update', [$evaluation, $elsewhere]), ['outcome' => 'met'])->assertNotFound();

    expect($api->refresh()->outcome)->toBeNull()
        ->and($elsewhere->refresh()->outcome)->toBeNull();
});

it('refuses Outcomes and Completing to anyone but the effective Manager, the Employee included', function () {
    [$ben, $cara, $evaluation, $api] = carasEvaluation();
    $form = assessmentForm('exceeded', 'My best year.');

    foreach ([$cara, Employee::query()->where('name', 'Ada')->sole(), employeeHoldingAdmin()] as $other) {
        actingAs($other)->put(goalOutcomeRoute($api), ['outcome' => 'exceeded'])->assertForbidden();
        actingAs($other)->put(route('performance-evaluations.assessment.update', $evaluation), $form)->assertForbidden();
        actingAs($other)->post(route('performance-evaluations.completion.store', $evaluation), $form)->assertForbidden();
    }
    expect($evaluation->refresh()->state)->toBe(PerformanceEvaluationState::Open)
        ->and($evaluation->outcome)->toBeNull()
        ->and($api->refresh()->outcome)->toBeNull();
});

it('lets nobody change a Completed Evaluation: its Goals, Outcomes or summary', function () {
    $ben = managerBen();
    $evaluation = PerformanceEvaluation::factory()->of(Employee::factory()->startedLongAgo()->reportingTo($ben)->create())->completed()->create();
    $goal = $evaluation->goals()->firstOrFail();

    actingAs($ben)->get(route('performance-evaluations.show', $evaluation))
        ->assertInertia(fn (Assert $page) => $page->where('evaluation.state', 'completed')->where('canEdit', false)->where('canChangeProgress', false));
    actingAs($ben)->post(route('performance-evaluations.goals.store', $evaluation), ['title' => 'Late', 'description' => 'Too late'])->assertForbidden();
    actingAs($ben)->put(route('performance-evaluations.goals.update', [$evaluation, $goal]), ['title' => 'Late', 'description' => 'Too late'])->assertForbidden();
    actingAs($ben)->delete(route('performance-evaluations.goals.destroy', [$evaluation, $goal]))->assertForbidden();
    actingAs($ben)->put(goalOutcomeRoute($goal), ['outcome' => 'not-met'])->assertForbidden();
    actingAs($ben)->put(route('performance-evaluations.assessment.update', $evaluation), assessmentForm('not-met', 'Changed'))->assertForbidden();
    actingAs($ben)->post(route('performance-evaluations.completion.store', $evaluation), assessmentForm('not-met', 'Changed'))->assertForbidden();

    expect($evaluation->refresh()->outcome)->toBe(Outcome::Met)
        ->and($evaluation->goals()->count())->toBe(2)
        ->and($goal->refresh()->outcome)->toBe(Outcome::Met);
});

it('shows the Employee no Outcome or summary until Completed, while the Manager sees what they have written', function () {
    [$ben, $cara, $evaluation, $api, $mentoring] = carasEvaluation();
    actingAs($ben)->put(goalOutcomeRoute($api), ['outcome' => 'met']);
    actingAs($ben)->put(route('performance-evaluations.assessment.update', $evaluation), assessmentForm('met', 'Draft.'));

    actingAs($cara)->get(route('performance-evaluations.show', $evaluation))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canSeeOutcomes', false)
            ->where('evaluation.goals.0.outcome', null)
            ->where('evaluation.outcome', null)
            ->where('evaluation.summary', null));
    actingAs($ben)->get(route('performance-evaluations.show', $evaluation))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canSeeOutcomes', true)
            ->where('evaluation.goals.0.outcome', 'met')
            ->where('evaluation.outcome', 'met')
            ->where('evaluation.summary', 'Draft.'));
    actingAs($cara)->get(route('my-performance-evaluations.index'))
        ->assertInertia(fn (Assert $page) => $page->where('evaluations.0.outcome', null));

    actingAs($ben)->put(goalOutcomeRoute($mentoring), ['outcome' => 'exceeded']);
    actingAs($ben)->post(route('performance-evaluations.completion.store', $evaluation), assessmentForm('exceeded', 'A great year.'));

    actingAs($cara)->get(route('performance-evaluations.show', $evaluation))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canSeeOutcomes', true)
            ->where('evaluation.goals.1.outcome', 'exceeded')
            ->where('evaluation.outcome', 'exceeded')
            ->where('evaluation.summary', 'A great year.')
            ->where('evaluation.completed_at', '2026-12-01'));
    actingAs($cara)->get(route('my-performance-evaluations.index'))
        ->assertInertia(fn (Assert $page) => $page->where('evaluations.0.state', 'completed')->where('evaluations.0.outcome', 'exceeded'));
    actingAs($ben)->get(route('performance-evaluations.index'))
        ->assertInertia(fn (Assert $page) => $page->where('employees.data.0.evaluation.outcome', 'exceeded'));
});

it('lets a Manager be removed once the Employee\'s only Evaluation is Completed', function () {
    $cara = Employee::factory()->startedLongAgo()->reportingTo(Employee::factory()->create())->create(['name' => 'Cara Lane']);
    PerformanceEvaluation::factory()->of($cara)->completed()->create();

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), employeeRecordChanging($cara, ['manager_id' => null]))
        ->assertSessionHasNoErrors();
    expect($cara->refresh()->manager_id)->toBeNull();
});
