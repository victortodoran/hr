<?php

use App\Actions\AssessPerformanceEvaluation;
use App\Enums\Outcome;
use App\Enums\PerformanceEvaluationState;
use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\Performance\PerformanceEvaluationCompletionController;
use App\Http\Controllers\Performance\PerformanceEvaluationController;
use App\Http\Controllers\Performance\PerformanceEvaluationReopeningController;
use App\Models\Employee;
use App\Models\PerformanceEvaluation;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(PerformanceEvaluationController::class, PerformanceEvaluationReopeningController::class, PerformanceEvaluationCompletionController::class);

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

it('shows the Manager the kept Outcome after a Reopen, and Completes it again when the same one is picked', function () {
    $ben = managerBen();
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara']);
    $evaluation = PerformanceEvaluation::factory()->of($cara)->completed()->create();
    app(AssessPerformanceEvaluation::class)->reopen($evaluation);

    actingAs($ben);

    visit(route('performance-evaluations.show', $evaluation))
        ->assertSeeIn('#evaluation-outcome', 'Met')
        ->assertDontSee('Give every Goal an Outcome and write the summary to complete it.')
        ->click('#evaluation-outcome')
        ->click('internal:role=option[name="Met"s]')
        ->assertSee('Complete Cara\'s 2026 Performance Evaluation as Met?')
        ->click('Keep it Open')
        ->assertSeeIn('#evaluation-outcome', 'Met')
        ->click('#evaluation-outcome')
        ->click('internal:role=option[name="Met"s]')
        ->click('internal:role=dialog >> internal:role=button[name="Complete"s]')
        ->assertSee('Performance Evaluation completed.')
        ->assertNoJavaScriptErrors();

    $evaluation->refresh();
    expect($evaluation->state)->toBe(PerformanceEvaluationState::Completed)
        ->and($evaluation->outcome)->toBe(Outcome::Met);
});
