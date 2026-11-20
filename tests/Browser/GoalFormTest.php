<?php

use App\Http\Controllers\Performance\GoalController;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(GoalController::class);

beforeEach(fn () => travelTo('2026-12-01 09:00'));

it('adds a Goal in the sheet over its Performance Evaluation, keeping the description\'s line breaks', function () {
    $ben = managerBen();
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara Lungu']);
    $evaluation = PerformanceEvaluation::factory()->of($cara)->create();

    actingAs($ben);

    visit(route('performance-evaluations.show', $evaluation))
        ->click('internal:role=link[name="Add Goal"s]')
        ->assertPathIs(route('performance-evaluations.goals.create', $evaluation, false))
        ->assertSeeIn('[data-slot="form-sheet"]', "Cara Lungu's 2026 Performance Evaluation")
        ->assertSee('Say what done looks like by the end of the year')
        ->fill('#goal-title', 'Lead the Checkout API')
        ->fill('#goal-description', "Own its design.\nShip it by October.")
        ->click('internal:role=dialog >> internal:role=button[name="Add Goal"s]')
        ->assertSee('Goal added.')
        ->assertPathIs(route('performance-evaluations.show', $evaluation, false))
        ->assertMissing('[data-slot="form-sheet"]')
        ->assertSee('Lead the Checkout API')
        ->assertNoJavaScriptErrors();

    expect($evaluation->goals()->sole()->description)->toBe("Own its design.\nShip it by October.");
});

it('opens the Add Goal sheet from its own URL on a phone, full width with no sideways scroll, and Cancel returns to the Performance Evaluation', function () {
    $ben = managerBen();
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara']);
    $evaluation = PerformanceEvaluation::factory()->of($cara)->create();

    actingAs($ben);

    visit(route('performance-evaluations.goals.create', $evaluation))
        ->resize(390, 844)
        ->assertVisible('#goal-title')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->click('internal:role=dialog >> internal:role=button[name="Cancel"s]')
        ->assertMissing('#goal-title')
        ->assertPathIs(route('performance-evaluations.show', $evaluation, false))
        ->assertNoJavaScriptErrors();

    expect($evaluation->goals()->count())->toBe(0);
});

it('edits a Goal in the sheet on its page from the card\'s Edit, keeps the sheet open on an error, and starts again from the saved Goal', function () {
    $ben = managerBen();
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara']);
    $evaluation = PerformanceEvaluation::factory()->of($cara)->create();
    $goal = Goal::factory()->for($evaluation)->create(['title' => 'Lead the Checkout API', 'description' => 'Own its design.']);

    actingAs($ben);

    visit(route('performance-evaluations.show', $evaluation))
        ->click('internal:role=button[name="More for Lead the Checkout API"s]')
        ->click('internal:role=menuitem[name="Edit"s]')
        ->assertPathIs(route('performance-evaluations.goals.show', [$evaluation, $goal], false))
        ->assertSee('Edit Goal')
        ->assertValue('#goal-title', 'Lead the Checkout API')
        ->fill('#goal-title', '   ')
        ->click('internal:role=dialog >> internal:role=button[name="Save Goal"s]')
        ->assertSee('The title field is required.')
        ->assertVisible('#goal-title')
        ->fill('#goal-title', 'Lead the Payments API')
        ->click('internal:role=dialog >> internal:role=button[name="Cancel"s]')
        ->assertMissing('#goal-title')
        ->assertSee('Own its design.')
        ->click('internal:role=link[name="Edit"s]')
        ->assertValue('#goal-title', 'Lead the Checkout API')
        ->assertDontSee('The title field is required.')
        ->assertNoJavaScriptErrors();

    expect($goal->fresh()?->title)->toBe('Lead the Checkout API');
});
