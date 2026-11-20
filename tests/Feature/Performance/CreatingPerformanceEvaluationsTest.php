<?php

use App\Actions\Performance\CreatePerformanceEvaluation;
use App\Enums\PerformanceEvaluationState;
use App\Http\Controllers\Performance\PerformanceEvaluationController;
use App\Http\Requests\Performance\PerformanceEvaluationRequest;
use App\Models\Employee;
use App\Models\PerformanceEvaluation;
use App\Pages\Performance\PerformanceEvaluationFormPage;
use App\Policies\PerformanceEvaluationPolicy;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(CreatePerformanceEvaluation::class, PerformanceEvaluationController::class, PerformanceEvaluationRequest::class, PerformanceEvaluationFormPage::class, PerformanceEvaluationPolicy::class);

beforeEach(fn () => travelTo('2026-12-01 09:00'));

/**
 * Cara, who started long ago and reports to the given Manager, ready to be
 * given a Performance Evaluation.
 */
function caraForANewEvaluation(Employee $manager): Employee
{
    return Employee::factory()->startedLongAgo()->reportingTo($manager)->create(['name' => 'Cara']);
}

it('opens the New Performance Evaluation dialog over the list, offering this year and next, each with the Employees the viewer effectively manages who have no Evaluation for it yet', function () {
    $ben = managerBen();
    $cara = caraForANewEvaluation($ben);
    $dan = Employee::factory()->reportingTo($ben)->create(['name' => 'Dan', 'start_date' => '2026-12-14']);
    $gus = Employee::factory()->reportingTo($ben)->create(['name' => 'Gus', 'start_date' => '2027-01-04']);
    $ola = Employee::factory()->reportingTo($ben)->create(['name' => 'Ola', 'start_date' => '2020-01-06', 'leaving_date' => '2026-12-31']);
    Employee::factory()->startedLongAgo()->create(['name' => 'Zed']);
    PerformanceEvaluation::factory()->of($cara)->create(['year' => 2026]);

    actingAs($ben)->get(route('performance-evaluations.create', ['year' => 2027, 'search' => 'Ca']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('performance-evaluations/Index')
            ->where('creating', true)
            ->where('year', 2027)
            ->where('search', 'Ca')
            ->where('employees.data', [])
            ->where('creatableYears', [
                ['year' => 2026, 'employees' => [['id' => $dan->id, 'name' => 'Dan'], ['id' => $ola->id, 'name' => 'Ola']]],
                ['year' => 2027, 'employees' => [['id' => $cara->id, 'name' => 'Cara'], ['id' => $dan->id, 'name' => 'Dan'], ['id' => $gus->id, 'name' => 'Gus']]],
            ]));
});

it('offers creating Performance Evaluations only while someone the viewer manages still lacks one for this year or next', function () {
    $ben = managerBen();
    $cara = caraForANewEvaluation($ben);
    PerformanceEvaluation::factory()->of($cara)->create(['year' => 2026]);

    actingAs($ben)->get(route('performance-evaluations.create'))->assertOk();

    PerformanceEvaluation::factory()->of($cara)->create(['year' => 2027]);

    actingAs($ben)->get(route('performance-evaluations.index'))
        ->assertInertia(fn (Assert $page) => $page->where('canCreate', false));
    actingAs($ben)->get(route('performance-evaluations.create'))->assertForbidden();
    actingAs($cara)->get(route('performance-evaluations.create'))->assertForbidden();
});

it('lets the effective Manager create an Open Performance Evaluation with no Goals for this year or next, and opens it', function (int $year) {
    $ben = managerBen();
    $cara = caraForANewEvaluation($ben);

    actingAs($ben)->post(route('performance-evaluations.store'), ['year' => $year, 'employee_id' => $cara->id])
        ->assertRedirect(route('performance-evaluations.show', PerformanceEvaluation::query()->sole()))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Performance Evaluation created.']);

    $evaluation = PerformanceEvaluation::query()->sole();
    expect($evaluation->employee_id)->toBe($cara->id)
        ->and($evaluation->year)->toBe($year)
        ->and($evaluation->state)->toBe(PerformanceEvaluationState::Open)
        ->and($evaluation->goals()->count())->toBe(0);
})->with([2026, 2027]);

it('refuses a year other than this one or the next', function (int $year) {
    $ben = managerBen();
    $cara = caraForANewEvaluation($ben);

    actingAs($ben)->post(route('performance-evaluations.store'), ['year' => $year, 'employee_id' => $cara->id])
        ->assertSessionHasErrors(['year' => 'Choose this year or next year.']);
    expect(PerformanceEvaluation::query()->exists())->toBeFalse();
})->with([2025, 2028]);

it('refuses a second Performance Evaluation for the same year', function () {
    $ben = managerBen();
    $cara = caraForANewEvaluation($ben);
    PerformanceEvaluation::factory()->of($cara)->create(['year' => 2027]);

    actingAs($ben)->post(route('performance-evaluations.store'), ['year' => 2027, 'employee_id' => $cara->id])
        ->assertSessionHasErrors(['employee_id' => 'Cara already has a 2027 Performance Evaluation.']);
    expect(PerformanceEvaluation::query()->count())->toBe(1);
});

it('refuses creating a Performance Evaluation for anyone but the effective Manager, the Employee included', function (Closure $actor) {
    $ben = managerBen();
    $cara = caraForANewEvaluation($ben);

    actingAs($actor($ben, $cara))->post(route('performance-evaluations.store'), ['year' => 2026, 'employee_id' => $cara->id])
        ->assertSessionHasErrors(['employee_id' => 'Choose one of the people you\'re the Manager of who work here in 2026.']);
    expect(PerformanceEvaluation::query()->exists())->toBeFalse();
})->with([
    'the Employee themselves' => [fn (Employee $ben, Employee $cara) => $cara],
    'their Manager\'s Manager' => [fn (Employee $ben, Employee $cara) => Employee::query()->where('name', 'Ada')->sole()],
    'an Admin' => [fn (Employee $ben, Employee $cara) => employeeHoldingAdmin()],
]);

it('keeps from someone who doesn\'t manage the Employee whether they have an Evaluation for the year', function () {
    $ben = managerBen();
    $cara = caraForANewEvaluation($ben);
    PerformanceEvaluation::factory()->of($cara)->create(['year' => 2026]);

    actingAs(Employee::factory()->startedLongAgo()->create())->post(route('performance-evaluations.store'), ['year' => 2026, 'employee_id' => $cara->id])
        ->assertSessionHasErrors(['employee_id' => 'Choose one of the people you\'re the Manager of who work here in 2026.']);
});

it('refuses a year the Employee doesn\'t work for the company in', function () {
    $ben = managerBen();
    $gus = Employee::factory()->reportingTo($ben)->create(['start_date' => '2027-01-04']);

    actingAs($ben)->post(route('performance-evaluations.store'), ['year' => 2026, 'employee_id' => $gus->id])
        ->assertSessionHasErrors('employee_id');
    actingAs($ben)->post(route('performance-evaluations.store'), ['year' => 2027, 'employee_id' => $gus->id])->assertRedirect();
    expect($gus->performanceEvaluations()->sole()->year)->toBe(2027);
});

it('refuses an Employee who doesn\'t exist', function () {
    actingAs(managerBen())->post(route('performance-evaluations.store'), ['year' => 2026, 'employee_id' => 999999])
        ->assertSessionHasErrors(['employee_id' => 'Choose one of the people you\'re the Manager of.']);
});

it('refuses with a validation error a Performance Evaluation created for the year since the form was checked', function () {
    $cara = caraForANewEvaluation(managerBen());
    PerformanceEvaluation::factory()->of($cara)->create(['year' => 2026]);

    expect(fn () => app(CreatePerformanceEvaluation::class)->create($cara, 2026))
        ->toThrow(ValidationException::class, 'Cara already has a 2026 Performance Evaluation.');
    expect($cara->performanceEvaluations()->count())->toBe(1);
});
