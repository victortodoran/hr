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
    $cara = Employee::factory()->reportingTo($ben)->create(['name' => 'Cara Lindqvist', 'start_date' => '2026-01-05']);
    $evaluation = PerformanceEvaluation::factory()->of($cara)->create();
    PerformanceEvaluation::factory()->of(Employee::factory()->reportingTo($ben)->create(['name' => 'Dan Last-Year', 'start_date' => '2020-01-06', 'leaving_date' => '2025-12-31']))->create(['year' => 2025]);

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

it('shows an Open Evaluation in the neutral grey, not the Pending amber', function () {
    $ben = managerBen();
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara']);
    PerformanceEvaluation::factory()->of($cara)->create();

    actingAs($ben);

    visit(route('performance-evaluations.index'))
        ->assertSeeIn('[data-slot="status-badge"]', 'Open')
        ->assertScript("document.querySelector('[data-slot=\"status-badge\"]').classList.contains('bg-status-closed')", true)
        ->assertNoJavaScriptErrors();
});

it('creates a Report\'s missing Evaluation from their None yet row, and finds Completed ones by state', function () {
    $ben = managerBen();
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara None']);
    PerformanceEvaluation::factory()->of(Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Eva Done']))->completed()->create();

    actingAs($ben);

    visit(route('performance-evaluations.index'))
        ->assertSee('None yet')
        ->click('[aria-label="State"]')
        ->click('internal:role=option[name="Completed"s]')
        ->assertQueryStringHas('state', 'completed')
        ->assertSee('Eva Done')
        ->assertDontSee('Cara None')
        ->click('[aria-label="State"]')
        ->click('internal:role=option[name="None yet"s]')
        ->assertSee('Cara None')
        ->click("internal:role=link[name=\"Create Cara None's 2026 Evaluation\"s]")
        ->assertPathIs(route('performance-evaluations.create', absolute: false))
        ->assertSeeIn('#evaluation-employee', 'Cara None')
        ->click('Create Evaluation')
        ->assertPathIs(route('performance-evaluations.show', $cara->performanceEvaluations()->firstOrFail(), false))
        ->assertNoJavaScriptErrors();
});
