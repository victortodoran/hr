<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\Performance\PerformanceEvaluationController;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;
use App\Policies\PerformanceEvaluationPolicy;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(PerformanceEvaluationController::class, PerformanceEvaluationPolicy::class, Permission::class);

beforeEach(fn () => travelTo('2026-12-01 09:00'));

/**
 * Ana, who manages Ben, who manages Cara, each with an Open 2026
 * Performance Evaluation holding one Goal, and Zed, who reports to nobody
 * among them.
 *
 * @return array{Employee, Employee, Employee, Employee}
 */
function performanceLine(): array
{
    $ana = Employee::factory()->startedLongAgo()->create(['name' => 'Ana']);
    $ben = Employee::factory()->startedLongAgo()->reportingTo($ana)->create(['name' => 'Ben']);
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara']);
    $zed = Employee::factory()->startedLongAgo()->reportingTo(Employee::factory()->startedLongAgo()->create(['name' => 'Yan']))->create(['name' => 'Zed']);

    foreach ([$ben, $cara, $zed] as $employee) {
        PerformanceEvaluation::factory()->of($employee)->has(Goal::factory())->create();
    }

    return [$ana, $ben, $cara, $zed];
}

it('lets someone with view performance evaluations for everyone see the page and everyone else on it, read-only', function () {
    [, $ben, $cara, $zed] = performanceLine();
    $dana = employeeWith([Permission::ViewPerformanceEvaluations->value => Scope::Everyone], ['name' => 'Dana', 'start_date' => '2025-01-06']);

    actingAs($dana)->get(route('my-performance-evaluations.index'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canSeePerformanceEvaluations', true));
    actingAs($dana)->get(route('performance-evaluations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('employees.data', fn (Collection $rows): bool => $rows->pluck('name')->all() === ['Ben', 'Cara', 'Zed'])
            ->where('canCreate', false));

    $evaluation = $cara->performanceEvaluations()->sole();
    actingAs($dana)->get(route('performance-evaluations.show', $evaluation))
        ->assertInertia(fn (Assert $page) => $page->where('canEdit', false)->where('isOwn', false));
    actingAs($dana)->get(route('performance-evaluations.create'))->assertForbidden();
    actingAs($dana)->post(route('performance-evaluations.store'), ['year' => 2027, 'employee_id' => $ben->id])->assertSessionHasErrors('employee_id');
    actingAs($dana)->post(route('performance-evaluations.goals.store', $evaluation), ['title' => 'From HR', 'description' => 'Not allowed'])->assertForbidden();
    actingAs($dana)->put(route('performance-evaluations.assessment.update', $evaluation), ['goals' => [], 'outcome' => 'met', 'summary' => 'No'])->assertForbidden();
    actingAs($dana)->post(route('performance-evaluations.completion.store', $evaluation), ['goals' => [], 'outcome' => 'met', 'summary' => 'No'])->assertForbidden();
    expect($evaluation->goals()->count())->toBe(1)
        ->and($zed->performanceEvaluations()->count())->toBe(1);
});

it('lets a Manager holding it for reports read Reports further down, while still writing their own Direct Reports\'', function () {
    [$ana, $ben, $cara, $zed] = performanceLine();
    giveRole($ana, [Permission::ViewPerformanceEvaluations->value => Scope::Reports]);

    actingAs($ana)->get(route('performance-evaluations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->count('employees.data', 2)
            ->where('employees.data.0.name', 'Ben')
            ->where('employees.data.1.name', 'Cara'));
    actingAs($ana)->get(route('performance-evaluations.show', $cara->performanceEvaluations()->sole()))
        ->assertInertia(fn (Assert $page) => $page->where('canEdit', false));
    actingAs($ana)->get(route('performance-evaluations.show', $ben->performanceEvaluations()->sole()))
        ->assertInertia(fn (Assert $page) => $page->where('canEdit', true));
    actingAs($ana)->get(route('performance-evaluations.show', $zed->performanceEvaluations()->sole()))->assertForbidden();
});

it('shows a reader Goals but no Outcomes or summary until the Evaluation is Completed', function () {
    [, , $cara] = performanceLine();
    $open = $cara->performanceEvaluations()->sole();
    $open->goals()->update(['outcome' => 'met']);
    $open->forceFill(['outcome' => 'met', 'summary' => 'Draft'])->save();
    $completed = PerformanceEvaluation::factory()->of($cara)->completed()->create(['year' => 2025]);
    $dana = employeeWith([Permission::ViewPerformanceEvaluations->value => Scope::Everyone]);

    actingAs($dana)->get(route('performance-evaluations.show', $open))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canSeeOutcomes', false)
            ->where('evaluation.goals.0.title', $open->goals()->sole()->title)
            ->where('evaluation.goals.0.outcome', null)
            ->where('evaluation.outcome', null)
            ->where('evaluation.summary', null));
    actingAs($dana)->get(route('performance-evaluations.show', $completed))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canSeeOutcomes', true)
            ->where('evaluation.outcome', 'met')
            ->where('evaluation.summary', $completed->summary));
});

it('gives nothing extra with the own Scope, and refuses the page to someone who neither manages nor reads anyone', function () {
    [, , $cara] = performanceLine();
    $eli = employeeWith([Permission::ViewPerformanceEvaluations->value => Scope::Own]);
    $nobody = Employee::factory()->create();

    actingAs($eli)->get(route('my-performance-evaluations.index'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canSeePerformanceEvaluations', false));
    actingAs($eli)->get(route('performance-evaluations.index'))->assertForbidden();
    actingAs($eli)->get(route('performance-evaluations.show', $cara->performanceEvaluations()->sole()))->assertForbidden();
    actingAs($nobody)->get(route('performance-evaluations.index'))->assertForbidden();
});
