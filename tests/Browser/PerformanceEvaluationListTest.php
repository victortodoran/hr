<?php

use App\Http\Controllers\Performance\PerformanceEvaluationController;
use App\Models\Employee;
use App\Models\PerformanceEvaluation;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(PerformanceEvaluationController::class);

beforeEach(fn () => travelTo('2026-12-01 09:00'));

it('opens an Evaluation from anywhere on its row', function () {
    $ben = managerBen();
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara Lindqvist']);
    $evaluation = PerformanceEvaluation::factory()->of($cara)->create();

    actingAs($ben);

    visit(route('performance-evaluations.index'))
        ->assertSee('CL')
        ->assertScript(<<<'JS'
            (() => {
                const status = document.querySelector('tbody tr td:last-child').getBoundingClientRect();
                const hit = document.elementFromPoint(status.left + status.width / 2, status.top + status.height / 2);

                return hit?.closest('a')?.textContent.trim() === 'Cara Lindqvist';
            })()
            JS)
        ->assertNoJavaScriptErrors()
        ->click('internal:role=link[name="Cara Lindqvist"s]')
        ->assertPathIs(route('performance-evaluations.show', $evaluation, false))
        ->assertNoJavaScriptErrors();
});

it('fits the list on a phone without scrolling the page sideways', function () {
    $ben = managerBen();
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara Alexandrescu-Whitfield-Lindqvist']);
    PerformanceEvaluation::factory()->of($cara)->create();

    actingAs($ben);

    visit(route('performance-evaluations.index'))
        ->on()->mobile()
        ->assertSee('Cara Alexandrescu-Whitfield-Lindqvist')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth')
        ->assertNoJavaScriptErrors();
});
