<?php

use App\Http\Controllers\Performance\PerformanceEvaluationController;
use App\Models\Employee;
use App\Models\PerformanceEvaluation;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(PerformanceEvaluationController::class);

beforeEach(fn () => travelTo('2026-12-01 09:00'));

it('opens an Evaluation from its Employee\'s name, and shows another year from the filter bar', function () {
    $ben = managerBen();
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara Lindqvist']);
    $evaluation = PerformanceEvaluation::factory()->of($cara)->create();
    PerformanceEvaluation::factory()->of(Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Dan Last-Year']))->create(['year' => 2025]);

    actingAs($ben);

    visit(route('performance-evaluations.index'))
        ->assertDontSee('Dan Last-Year')
        ->click('[aria-label="Year"]')
        ->click('internal:role=option[name="2025"s]')
        ->assertQueryStringHas('year', '2025')
        ->assertSee('Dan Last-Year')
        ->assertDontSee('Cara Lindqvist')
        ->click('[aria-label="Year"]')
        ->click('internal:role=option[name="2026"s]')
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
