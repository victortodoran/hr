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

it('shows an Open Evaluation in the neutral grey with its hollow-circle icon, not the Pending amber', function () {
    $ben = managerBen();
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara']);
    PerformanceEvaluation::factory()->of($cara)->create();

    actingAs($ben);

    visit(route('performance-evaluations.index'))
        ->assertSeeIn('[data-slot="status-badge"]', 'Open')
        ->assertScript("document.querySelector('[data-slot=\"status-badge\"]').classList.contains('bg-status-neutral')", true)
        ->assertPresent('[data-slot="status-badge"][data-tone="neutral"] svg')
        ->assertNoJavaScriptErrors();
});

it('leaves out a Report without an Evaluation, finds Completed ones by state, and starts the missing one from New Performance Evaluation, keeping the filters behind the dialog', function () {
    $ben = managerBen();
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara Missing']);
    PerformanceEvaluation::factory()->of(Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Eva Done']))->completed()->create();
    PerformanceEvaluation::factory()->of(Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Dan Open']))->create();

    actingAs($ben);

    visit(route('performance-evaluations.index'))
        ->assertSee('Dan Open')
        ->assertDontSee('Cara Missing')
        ->click('[aria-label="State"]')
        ->assertDontSee('None yet')
        ->click('internal:role=option[name="Completed"s]')
        ->assertQueryStringHas('state', 'completed')
        ->assertSee('Eva Done')
        ->assertDontSee('Dan Open')
        ->click('internal:role=link[name="New Performance Evaluation…"s]')
        ->assertPathIs(route('performance-evaluations.create', absolute: false))
        ->assertQueryStringHas('state', 'completed')
        ->assertSee('Eva Done')
        ->click('#evaluation-employee')
        ->click('internal:role=option[name="Cara Missing"s]')
        ->click('internal:role=dialog >> internal:role=button[name="New Performance Evaluation"s]')
        ->assertSee("Cara Missing's 2026 Performance Evaluation")
        ->assertPathIs(route('performance-evaluations.show', $cara->performanceEvaluations()->firstOrFail(), false))
        ->assertNoJavaScriptErrors();
});

it('opens the New Performance Evaluation dialog from its own URL on a phone without scrolling sideways, and closing it returns to the list', function () {
    $ben = managerBen();
    Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara Alexandrescu-Whitfield-Lindqvist']);

    actingAs($ben);

    visit(route('performance-evaluations.create'))
        ->resize(390, 844)
        ->assertSee('New Performance Evaluation')
        ->assertVisible('#evaluation-employee')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->click('internal:role=dialog >> internal:role=button[name="Cancel"s]')
        ->assertMissing('#evaluation-employee')
        ->assertPathIs(route('performance-evaluations.index', absolute: false))
        ->assertNoJavaScriptErrors();
});
