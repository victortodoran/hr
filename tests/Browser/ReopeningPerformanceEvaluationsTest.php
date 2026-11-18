<?php

use App\Enums\PerformanceEvaluationState;
use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\Performance\PerformanceEvaluationController;
use App\Http\Controllers\Performance\PerformanceEvaluationReopeningController;
use App\Models\Employee;
use App\Models\PerformanceEvaluation;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(PerformanceEvaluationController::class, PerformanceEvaluationReopeningController::class);

beforeEach(fn () => travelTo('2026-12-01 09:00'));

it('asks before reopening a Completed Evaluation, then shows it Open', function () {
    $cara = Employee::factory()->startedLongAgo()->reportingTo(managerBen())->create(['name' => 'Cara']);
    $evaluation = PerformanceEvaluation::factory()->of($cara)->completed()->create();

    actingAs(employeeWith([Permission::ReopenPerformanceEvaluations->value => Scope::Everyone], ['name' => 'Rae']));

    visit(route('performance-evaluations.show', $evaluation))
        ->assertSee('Completed')
        ->click('internal:role=button[name="Reopen"s]')
        ->assertSee('Reopen Cara\'s 2026 Performance Evaluation?')
        ->click('internal:role=dialog >> internal:role=button[name="Reopen"s]')
        ->assertSee('Performance Evaluation reopened.')
        ->assertDontSee('Reopen')
        ->assertNoJavaScriptErrors();

    expect($evaluation->refresh()->state)->toBe(PerformanceEvaluationState::Open);
});
