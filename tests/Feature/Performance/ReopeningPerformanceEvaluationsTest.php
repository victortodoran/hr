<?php

use App\Actions\AssessPerformanceEvaluation;
use App\Enums\Outcome;
use App\Enums\PerformanceEvaluationState;
use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\Performance\PerformanceEvaluationController;
use App\Http\Controllers\Performance\PerformanceEvaluationReopeningController;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;
use App\Policies\PerformanceEvaluationPolicy;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(PerformanceEvaluationReopeningController::class, AssessPerformanceEvaluation::class, PerformanceEvaluationController::class, PerformanceEvaluationPolicy::class, Permission::class);

beforeEach(fn () => travelTo('2026-12-01 09:00'));

/**
 * Ben, who manages Cara, whose 2026 Performance Evaluation he has
 * Completed.
 *
 * @return array{Employee, Employee, PerformanceEvaluation}
 */
function carasCompletedEvaluation(): array
{
    $ben = Employee::factory()->startedLongAgo()->create(['name' => 'Ben']);
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara Lane']);
    $evaluation = PerformanceEvaluation::factory()->of($cara)->completed()->create(['summary' => 'A steady year.']);

    return [$ben, $cara, $evaluation];
}

it('lets someone with reopen performance evaluations send a Completed one back to Open, keeping what was written', function () {
    [$ben, $cara, $evaluation] = carasCompletedEvaluation();
    $rae = employeeWith([Permission::ReopenPerformanceEvaluations->value => Scope::Everyone], ['name' => 'Rae']);

    actingAs($rae)->get(route('performance-evaluations.index'))
        ->assertInertia(fn (Assert $page) => $page->where('employees.data', fn (Collection $rows): bool => $rows->pluck('name')->all() === ['Ben', 'Cara Lane']));
    actingAs($rae)->get(route('performance-evaluations.show', $evaluation))
        ->assertInertia(fn (Assert $page) => $page->where('canReopen', true)->where('canEdit', false));

    actingAs($rae)->post(route('performance-evaluations.reopening.store', $evaluation))
        ->assertRedirect(route('performance-evaluations.show', $evaluation));

    $evaluation->refresh();
    expect($evaluation->state)->toBe(PerformanceEvaluationState::Open)
        ->and($evaluation->completed_at)->toBeNull()
        ->and($evaluation->outcome)->toBe(Outcome::Met)
        ->and($evaluation->summary)->toBe('A steady year.')
        ->and($evaluation->goals()->pluck('outcome')->all())->toBe([Outcome::Met, Outcome::Met]);
    actingAs($ben)->get(route('performance-evaluations.show', $evaluation))
        ->assertInertia(fn (Assert $page) => $page->where('canEdit', true)->where('canReopen', false)->where('evaluation.summary', 'A steady year.'));

    actingAs($ben)->post(route('performance-evaluations.completion.store', $evaluation), [
        'goals' => $evaluation->goals->map(fn (Goal $goal): array => ['id' => $goal->id, 'outcome' => 'exceeded'])->all(),
        'outcome' => 'exceeded',
        'summary' => 'Better than first written.',
    ])->assertSessionHasNoErrors();
    expect($evaluation->refresh()->state)->toBe(PerformanceEvaluationState::Completed)
        ->and($evaluation->summary)->toBe('Better than first written.');
});

it('refuses reopening to the Manager alone, to the Employee themself, and outside the reopen Scope', function (Closure $reopener) {
    [$ben, $cara, $evaluation] = carasCompletedEvaluation();
    $actor = $reopener($ben, $cara);

    actingAs($actor)->post(route('performance-evaluations.reopening.store', $evaluation))->assertForbidden();
    expect($evaluation->refresh()->state)->toBe(PerformanceEvaluationState::Completed);
})->with([
    'the Manager' => [fn (Employee $ben) => $ben],
    'the Employee, even holding it for everyone' => [function (Employee $ben, Employee $cara): Employee {
        giveRole($cara, [Permission::ReopenPerformanceEvaluations->value => Scope::Everyone]);

        return $cara;
    }],
    'a holder for reports who doesn\'t manage her' => [fn () => employeeWith([Permission::ReopenPerformanceEvaluations->value => Scope::Reports])],
]);

it('lets a holder for reports reopen their Reports\' further down', function () {
    [$ben, , $evaluation] = carasCompletedEvaluation();
    $ana = Employee::factory()->startedLongAgo()->create(['name' => 'Ana']);
    $ben->update(['manager_id' => $ana->id]);
    giveRole($ana, [Permission::ReopenPerformanceEvaluations->value => Scope::Reports]);

    actingAs($ana)->post(route('performance-evaluations.reopening.store', $evaluation))->assertRedirect();
    expect($evaluation->refresh()->state)->toBe(PerformanceEvaluationState::Open);
});

it('refuses reopening one that is still Open', function () {
    $cara = Employee::factory()->startedLongAgo()->reportingTo(Employee::factory()->create())->create();
    $evaluation = PerformanceEvaluation::factory()->of($cara)->create();
    $rae = employeeWith([Permission::ReopenPerformanceEvaluations->value => Scope::Everyone]);

    actingAs($rae)->get(route('performance-evaluations.show', $evaluation))
        ->assertInertia(fn (Assert $page) => $page->where('canReopen', false));
    actingAs($rae)->post(route('performance-evaluations.reopening.store', $evaluation))->assertForbidden();
});
