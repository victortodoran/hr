<?php

use App\Enums\PerformanceEvaluationState;
use App\Http\Controllers\Performance\GoalController;
use App\Http\Controllers\Performance\MyPerformanceEvaluationController;
use App\Http\Controllers\Performance\PerformanceEvaluationController;
use App\Http\Requests\Performance\GoalRequest;
use App\Http\Requests\Performance\PerformanceEvaluationListRequest;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;
use App\Policies\PerformanceEvaluationPolicy;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(MyPerformanceEvaluationController::class, PerformanceEvaluationController::class, GoalController::class, GoalRequest::class, PerformanceEvaluationListRequest::class, PerformanceEvaluationPolicy::class, PerformanceEvaluation::class);

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

it('shows every Employee only their own Performance Evaluations, newest first, with their Goal counts', function () {
    $cara = caraReportingTo(managerBen());
    $older = PerformanceEvaluation::factory()->of($cara)->create(['year' => 2025]);
    $current = PerformanceEvaluation::factory()->of($cara)->has(Goal::factory()->count(2))->create(['year' => 2026]);
    PerformanceEvaluation::factory()->create();

    actingAs($cara)->get(route('my-performance-evaluations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('performance-evaluations/Mine')
            ->where('evaluations', [
                ['id' => $current->id, 'year' => 2026, 'state' => 'open', 'goals_count' => 2, 'outcome' => null],
                ['id' => $older->id, 'year' => 2025, 'state' => 'open', 'goals_count' => 0, 'outcome' => null],
            ]));
});

it('lets an Employee read their own Performance Evaluation\'s Goals but not change them', function () {
    $cara = caraReportingTo(managerBen());
    $evaluation = PerformanceEvaluation::factory()->of($cara)->create();
    $goal = Goal::factory()->for($evaluation)->create(['title' => 'Lead the Checkout API', 'description' => 'Own its design.']);

    actingAs($cara)->get(route('performance-evaluations.show', $evaluation))
        ->assertInertia(fn (Assert $page) => $page
            ->component('performance-evaluations/Show')
            ->where('evaluation.goals', [['id' => $goal->id, 'title' => 'Lead the Checkout API', 'description' => 'Own its design.', 'outcome' => null]])
            ->where('isOwn', true)
            ->where('canManageGoals', false));

    actingAs($cara)->post(route('performance-evaluations.goals.store', $evaluation), ['title' => 'Mine', 'description' => 'Set by me'])->assertForbidden();
    actingAs($cara)->put(route('performance-evaluations.goals.update', [$evaluation, $goal]), ['title' => 'Mine', 'description' => 'Set by me'])->assertForbidden();
    actingAs($cara)->delete(route('performance-evaluations.goals.destroy', [$evaluation, $goal]))->assertForbidden();
    expect(storedGoals($evaluation))->toBe([['title' => 'Lead the Checkout API', 'description' => 'Own its design.']]);
});

it('refuses a Performance Evaluation to anyone but its Employee and their effective Manager', function () {
    $ben = managerBen();
    $evaluation = PerformanceEvaluation::factory()->of(caraReportingTo($ben))->create();

    actingAs($ben)->get(route('performance-evaluations.show', $evaluation))->assertOk();
    actingAs(Employee::factory()->create())->get(route('performance-evaluations.show', $evaluation))->assertForbidden();
    actingAs(Employee::query()->where('name', 'Ada')->sole())->get(route('performance-evaluations.show', $evaluation))->assertForbidden();
});

it('offers the Performance Evaluations page only to Employees who effectively manage someone', function () {
    $ben = managerBen();
    $cara = caraReportingTo($ben);

    actingAs($ben)->get(route('my-performance-evaluations.index'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canSeePerformanceEvaluations', true));
    actingAs($cara)->get(route('my-performance-evaluations.index'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canSeePerformanceEvaluations', false));
    actingAs($cara)->get(route('performance-evaluations.index'))->assertForbidden();
});

it('lists, for the year, the Employees the viewer effectively manages who work that year, with their Evaluation or whether it can be started', function () {
    $ben = managerBen();
    $cara = caraReportingTo($ben);
    $dan = Employee::factory()->reportingTo($ben)->create(['name' => 'Dan', 'start_date' => '2026-12-14']);
    Employee::factory()->reportingTo($ben)->create(['name' => 'Gus', 'start_date' => '2027-01-04']);
    Employee::factory()->reportingTo($ben)->create(['name' => 'Ola', 'start_date' => '2020-01-06', 'leaving_date' => '2025-12-31']);
    Employee::factory()->startedLongAgo()->create(['name' => 'Zed']);
    $evaluation = PerformanceEvaluation::factory()->of($cara)->has(Goal::factory()->count(3))->create();

    actingAs($ben)->get(route('performance-evaluations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('performance-evaluations/Index')
            ->where('year', 2026)
            ->where('years', [2026, 2025, 2024, 2023, 2022, 2021, 2020])
            ->where('employees.data', [
                ['id' => $cara->id, 'name' => 'Cara', 'evaluation' => ['id' => $evaluation->id, 'state' => 'open', 'goals_count' => 3, 'outcome' => null], 'can_start' => false],
                ['id' => $dan->id, 'name' => 'Dan', 'evaluation' => null, 'can_start' => true],
            ]));
});

it('shows an earlier year with the Employees who worked that year, and offers no start for it', function () {
    $ben = managerBen();
    $cara = caraReportingTo($ben);
    $ola = Employee::factory()->reportingTo($ben)->create(['name' => 'Ola', 'start_date' => '2020-01-06', 'leaving_date' => '2025-12-31']);
    PerformanceEvaluation::factory()->of($cara)->create(['year' => 2024]);

    actingAs($ben)->get(route('performance-evaluations.index', ['year' => 2025]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('year', 2025)
            ->where('years', [2026, 2025, 2024, 2023, 2022, 2021, 2020])
            ->where('employees.data', [
                ['id' => $cara->id, 'name' => 'Cara', 'evaluation' => null, 'can_start' => false],
                ['id' => $ola->id, 'name' => 'Ola', 'evaluation' => null, 'can_start' => false],
            ]));
});

it('refuses a year still to come on the Performance Evaluations page', function () {
    $ben = managerBen();
    caraReportingTo($ben);

    actingAs($ben)->get(route('performance-evaluations.index', ['year' => 2027]))->assertSessionHasErrors('year');
});

it('lists the Reports of a Manager who has left under the next Manager up', function () {
    $ana = Employee::factory()->startedLongAgo()->create(['name' => 'Ana']);
    $ben = Employee::factory()->startedLongAgo()->reportingTo($ana)->create(['name' => 'Ben', 'leaving_date' => '2026-10-31']);
    $cara = caraReportingTo($ben);

    actingAs($ana)->get(route('performance-evaluations.index'))
        ->assertInertia(fn (Assert $page) => $page->count('employees.data', 2)->where('employees.data.0.name', 'Ben')->where('employees.data.1.name', 'Cara'));
    actingAs($ana)->post(route('employees.performance-evaluations.store', $cara))->assertRedirect();
});

it('lets the effective Manager start an Employee\'s Performance Evaluation for this year, Open with no Goals', function () {
    $ben = managerBen();
    $cara = caraReportingTo($ben);

    actingAs($ben)->post(route('employees.performance-evaluations.store', $cara))
        ->assertRedirect(route('performance-evaluations.show', PerformanceEvaluation::query()->sole()))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Performance Evaluation started.']);

    $evaluation = PerformanceEvaluation::query()->sole();
    expect($evaluation->employee_id)->toBe($cara->id)
        ->and($evaluation->year)->toBe(2026)
        ->and($evaluation->state)->toBe(PerformanceEvaluationState::Open)
        ->and($evaluation->goals()->count())->toBe(0);
});

it('refuses starting a second Performance Evaluation for the same year', function () {
    $ben = managerBen();
    $cara = caraReportingTo($ben);
    PerformanceEvaluation::factory()->of($cara)->create();

    actingAs($ben)->post(route('employees.performance-evaluations.store', $cara))->assertForbidden();
    expect(PerformanceEvaluation::query()->count())->toBe(1);
});

it('refuses starting a Performance Evaluation for anyone but the effective Manager, the Employee included', function (Closure $actor) {
    $ben = managerBen();
    $cara = caraReportingTo($ben);

    actingAs($actor($ben, $cara))->post(route('employees.performance-evaluations.store', $cara))->assertForbidden();
    expect(PerformanceEvaluation::query()->exists())->toBeFalse();
})->with([
    'the Employee themselves' => [fn (Employee $ben, Employee $cara) => $cara],
    'their Manager\'s Manager' => [fn (Employee $ben, Employee $cara) => Employee::query()->where('name', 'Ada')->sole()],
    'an Admin' => [fn (Employee $ben, Employee $cara) => employeeWhoManagesRoles()],
]);

it('lets the effective Manager start this year\'s Performance Evaluation for a Future Employee who starts this year', function () {
    $ben = managerBen();
    $dan = Employee::factory()->reportingTo($ben)->create(['start_date' => '2026-12-14']);

    actingAs($ben)->post(route('employees.performance-evaluations.store', $dan))->assertRedirect();
    expect($dan->performanceEvaluations()->sole()->year)->toBe(2026);
});

it('refuses starting this year\'s Performance Evaluation for a Future Employee who starts next year', function () {
    $ben = managerBen();
    $gus = Employee::factory()->reportingTo($ben)->create(['start_date' => '2027-01-04']);

    actingAs($ben)->post(route('employees.performance-evaluations.store', $gus))->assertForbidden();
});

it('lets the effective Manager give, change and remove Goals while the Evaluation is Open', function () {
    $ben = managerBen();
    $evaluation = PerformanceEvaluation::factory()->of(caraReportingTo($ben))->create();

    actingAs($ben)->get(route('performance-evaluations.show', $evaluation))
        ->assertInertia(fn (Assert $page) => $page->where('isOwn', false)->where('canManageGoals', true));
    actingAs($ben)->get(route('performance-evaluations.goals.create', $evaluation))
        ->assertInertia(fn (Assert $page) => $page
            ->component('performance-evaluations/goals/Create')
            ->where('evaluation', ['id' => $evaluation->id, 'year' => 2026, 'employee' => ['id' => $evaluation->employee_id, 'name' => 'Cara']]));

    actingAs($ben)->post(route('performance-evaluations.goals.store', $evaluation), ['title' => ' Lead the Checkout API ', 'description' => 'Own its design.'])
        ->assertRedirect(route('performance-evaluations.show', $evaluation))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Goal given.']);
    $goal = $evaluation->goals()->sole();

    actingAs($ben)->get(route('performance-evaluations.goals.edit', [$evaluation, $goal]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('performance-evaluations/goals/Edit')
            ->where('goal', ['id' => $goal->id, 'title' => 'Lead the Checkout API', 'description' => 'Own its design.']));
    actingAs($ben)->put(route('performance-evaluations.goals.update', [$evaluation, $goal]), ['title' => 'Lead the Payments API', 'description' => 'Own its delivery.'])
        ->assertRedirect(route('performance-evaluations.show', $evaluation))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Goal changed.']);
    expect(storedGoals($evaluation))->toBe([['title' => 'Lead the Payments API', 'description' => 'Own its delivery.']]);

    actingAs($ben)->delete(route('performance-evaluations.goals.destroy', [$evaluation, $goal]))
        ->assertRedirect(route('performance-evaluations.show', $evaluation))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Goal removed.']);
    expect(storedGoals($evaluation))->toBe([]);
});

it('requires a Goal\'s title and description', function () {
    $ben = managerBen();
    $evaluation = PerformanceEvaluation::factory()->of(caraReportingTo($ben))->create();

    actingAs($ben)->post(route('performance-evaluations.goals.store', $evaluation), ['title' => '', 'description' => ''])
        ->assertSessionHasErrors(['title' => 'The title field is required.', 'description' => 'The description field is required.']);
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
