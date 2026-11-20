<?php

use App\Http\Controllers\Performance\GoalController;
use App\Http\Controllers\Performance\MyPerformanceEvaluationController;
use App\Http\Controllers\Performance\PerformanceEvaluationController;
use App\Http\Requests\Performance\GoalRequest;
use App\Http\Requests\Performance\PerformanceEvaluationListRequest;
use App\Http\Requests\Performance\PerformanceEvaluationRequest;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;
use App\Policies\PerformanceEvaluationPolicy;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(MyPerformanceEvaluationController::class, PerformanceEvaluationController::class, GoalController::class, GoalRequest::class, PerformanceEvaluationListRequest::class, PerformanceEvaluationRequest::class, PerformanceEvaluationPolicy::class, PerformanceEvaluation::class);

beforeEach(fn () => travelTo('2026-12-01 09:00'));

/**
 * Cara, who started long ago and reports to the given Manager.
 */
function caraReportingTo(Employee $manager): Employee
{
    return Employee::factory()->startedLongAgo()->reportingTo($manager)->create(['name' => 'Cara']);
}

/**
 * The Goals of the Performance Evaluation as stored, oldest first.
 *
 * @return array<int, array{title: string, description: string}>
 */
function storedGoals(PerformanceEvaluation $evaluation): array
{
    return $evaluation->goals()->get()->map(fn (Goal $goal): array => $goal->only('title', 'description'))->all();
}

it('lets an Employee read their own Performance Evaluation\'s Goals but not change them', function () {
    $cara = caraReportingTo(managerBen());
    $evaluation = PerformanceEvaluation::factory()->of($cara)->create();
    $goal = Goal::factory()->for($evaluation)->create(['title' => 'Lead the Checkout API', 'description' => 'Own its design.']);

    actingAs($cara)->get(route('performance-evaluations.show', $evaluation))
        ->assertInertia(fn (Assert $page) => $page
            ->component('performance-evaluations/Show')
            ->where('evaluation.goals', [['id' => $goal->id, 'title' => 'Lead the Checkout API', 'description' => 'Own its design.', 'progress' => 'not-started', 'comments_count' => 0, 'outcome' => null]])
            ->where('isOwn', true)
            ->where('canEdit', false));

    actingAs($cara)->post(route('performance-evaluations.goals.store', $evaluation), ['title' => 'Mine', 'description' => 'Set by me'])->assertForbidden();
    actingAs($cara)->put(route('performance-evaluations.goals.update', [$evaluation, $goal]), ['title' => 'Mine', 'description' => 'Set by me'])->assertForbidden();
    actingAs($cara)->delete(route('performance-evaluations.goals.destroy', [$evaluation, $goal]))->assertForbidden();
    expect(storedGoals($evaluation))->toBe([['title' => 'Lead the Checkout API', 'description' => 'Own its design.']]);
});

it('refuses a Performance Evaluation to the Manager\'s own Manager', function () {
    $evaluation = PerformanceEvaluation::factory()->of(caraReportingTo(managerBen()))->create();

    actingAs(Employee::query()->where('name', 'Ada')->sole())->get(route('performance-evaluations.show', $evaluation))->assertForbidden();
});

it('offers the Performance Evaluations page to an Employee who effectively manages someone', function () {
    $ben = managerBen();
    caraReportingTo($ben);

    actingAs($ben)->get(route('my-performance-evaluations.index'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canSeePerformanceEvaluations', true));
});

it('offers last year, this year and next on the Performance Evaluations page, with each Evaluation\'s Goal count', function () {
    $ben = managerBen();
    PerformanceEvaluation::factory()->of(caraReportingTo($ben))->has(Goal::factory()->count(3))->create();

    actingAs($ben)->get(route('performance-evaluations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('performance-evaluations/Index')
            ->where('years', [2027, 2026, 2025])
            ->where('canCreate', true)
            ->where('employees.data.0.evaluation.goals_count', 3));
});

it('lists a Former Employee\'s Evaluation for a past year', function () {
    $ben = managerBen();
    $ola = Employee::factory()->reportingTo($ben)->create(['name' => 'Ola', 'start_date' => '2020-01-06', 'leaving_date' => '2025-12-31']);
    $evaluation = PerformanceEvaluation::factory()->of($ola)->create(['year' => 2025]);

    actingAs($ben)->get(route('performance-evaluations.index', ['year' => 2025]))
        ->assertInertia(fn (Assert $page) => $page->where('employees.data.0.evaluation.id', $evaluation->id));
});

it('refuses the Performance Evaluations page for the year after next', function () {
    $ben = managerBen();
    caraReportingTo($ben);

    actingAs($ben)->get(route('performance-evaluations.index', ['year' => 2028]))->assertSessionHasErrors('year');
});

it('lists the Reports of a Manager who has left under the next Manager up', function () {
    $ana = Employee::factory()->startedLongAgo()->create(['name' => 'Ana']);
    $ben = Employee::factory()->startedLongAgo()->reportingTo($ana)->create(['name' => 'Ben', 'leaving_date' => '2026-10-31']);
    $cara = caraReportingTo($ben);
    PerformanceEvaluation::factory()->of($ben)->create();

    actingAs($ana)->get(route('performance-evaluations.create'))
        ->assertInertia(fn (Assert $page) => $page->where('creatableYears.0.employees', [['id' => $cara->id, 'name' => 'Cara']]));
    actingAs($ana)->post(route('performance-evaluations.store'), ['year' => 2026, 'employee_id' => $cara->id])->assertRedirect();
    expect($cara->performanceEvaluations()->sole()->year)->toBe(2026);
    actingAs($ana)->get(route('performance-evaluations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('employees.data', fn (Collection $rows): bool => $rows->pluck('name')->all() === ['Ben', 'Cara']));
});

it('lets the effective Manager give, change and remove Goals while the Evaluation is Open', function () {
    $ben = managerBen();
    $evaluation = PerformanceEvaluation::factory()->of(caraReportingTo($ben))->create();

    actingAs($ben)->get(route('performance-evaluations.show', $evaluation))
        ->assertInertia(fn (Assert $page) => $page->where('isOwn', false)->where('canEdit', true));
    actingAs($ben)->get(route('performance-evaluations.goals.create', $evaluation))
        ->assertInertia(fn (Assert $page) => $page
            ->component('performance-evaluations/Show')
            ->where('addingGoal', true)
            ->where('canEdit', true)
            ->where('evaluation.id', $evaluation->id)
            ->where('evaluation.employee', ['id' => $evaluation->employee_id, 'name' => 'Cara']));

    actingAs($ben)->post(route('performance-evaluations.goals.store', $evaluation), ['title' => ' Lead the Checkout API ', 'description' => 'Own its design.'])
        ->assertRedirect(route('performance-evaluations.show', $evaluation))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Goal added.']);
    $goal = $evaluation->goals()->sole();

    actingAs($ben)->put(route('performance-evaluations.goals.update', [$evaluation, $goal]), ['title' => 'Lead the Payments API', 'description' => 'Own its delivery.'])
        ->assertRedirect(route('performance-evaluations.goals.show', [$evaluation, $goal]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Goal saved.']);
    expect(storedGoals($evaluation))->toBe([['title' => 'Lead the Payments API', 'description' => 'Own its delivery.']]);

    actingAs($ben)->delete(route('performance-evaluations.goals.destroy', [$evaluation, $goal]))
        ->assertRedirect(route('performance-evaluations.show', $evaluation))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Goal deleted.']);
    expect(storedGoals($evaluation))->toBe([]);
});

it('refuses a Goal from another Performance Evaluation', function () {
    $ben = managerBen();
    $evaluation = PerformanceEvaluation::factory()->of(caraReportingTo($ben))->create();
    $elsewhere = Goal::factory()->create();

    actingAs($ben)->put(route('performance-evaluations.goals.update', [$evaluation, $elsewhere]), ['title' => 'Moved', 'description' => 'Moved'])->assertNotFound();
});

it('refuses Goals to someone who doesn\'t effectively manage the Employee', function () {
    $evaluation = PerformanceEvaluation::factory()->of(caraReportingTo(managerBen()))->create();

    actingAs(Employee::query()->where('name', 'Ada')->sole())
        ->post(route('performance-evaluations.goals.store', $evaluation), ['title' => 'Hers', 'description' => 'From above'])
        ->assertForbidden();
});

it('hands an Employee\'s Performance Evaluation to their new Manager', function () {
    $ben = managerBen();
    $cara = caraReportingTo($ben);
    $evaluation = PerformanceEvaluation::factory()->of($cara)->create();
    $hana = Employee::factory()->startedLongAgo()->create(['name' => 'Hana']);
    $cara->update(['manager_id' => $hana->id]);

    actingAs($hana)->post(route('performance-evaluations.goals.store', $evaluation), ['title' => 'New lead', 'description' => 'From Hana'])->assertRedirect();
    actingAs($ben)->post(route('performance-evaluations.goals.store', $evaluation), ['title' => 'Old lead', 'description' => 'From Ben'])->assertForbidden();
    expect(storedGoals($evaluation))->toBe([['title' => 'New lead', 'description' => 'From Hana']]);
});

it('lets the last effective Manager of a Former Employee still give Goals on their Evaluation', function () {
    $ben = managerBen();
    $eve = Employee::factory()->reportingTo($ben)->create(['name' => 'Eve', 'start_date' => '2020-01-06', 'leaving_date' => '2026-10-31']);
    $evaluation = PerformanceEvaluation::factory()->of($eve)->create();

    actingAs($ben)->get(route('performance-evaluations.index'))
        ->assertInertia(fn (Assert $page) => $page->where('employees.data.0.name', 'Eve'));
    actingAs($ben)->post(route('performance-evaluations.goals.store', $evaluation), ['title' => 'Hand over QA', 'description' => 'Before leaving'])->assertRedirect();
    expect(storedGoals($evaluation))->toHaveCount(1);
});
