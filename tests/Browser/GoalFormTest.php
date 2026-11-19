<?php

use App\Http\Controllers\Performance\GoalController;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(GoalController::class);

beforeEach(fn () => travelTo('2026-12-01 09:00'));

it('gives a Goal whose description keeps its line breaks', function () {
    $ben = managerBen();
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara Lungu']);
    $evaluation = PerformanceEvaluation::factory()->of($cara)->create();

    actingAs($ben);

    visit(route('performance-evaluations.goals.create', $evaluation))
        ->assertSee('Give Cara a Goal')
        ->assertSee('For the 2026 Performance Evaluation. Cara sees it as soon as you give it.')
        ->fill('#goal-title', 'Lead the Checkout API')
        ->fill('#goal-description', "Own its design.\nShip it by October.")
        ->click('Give Goal')
        ->assertSee('Goal given.')
        ->assertNoJavaScriptErrors();

    expect($evaluation->goals()->sole()->description)->toBe("Own its design.\nShip it by October.");
});

it('opens a Goal\'s fields on its page from the card\'s Change, keeps them open on an error, and puts the text back on Cancel', function () {
    $ben = managerBen();
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara']);
    $evaluation = PerformanceEvaluation::factory()->of($cara)->create();
    $goal = Goal::factory()->for($evaluation)->create(['title' => 'Lead the Checkout API', 'description' => 'Own its design.']);

    actingAs($ben);

    visit(route('performance-evaluations.show', $evaluation))
        ->click('internal:role=button[name="More for Lead the Checkout API"s]')
        ->click('internal:role=menuitem[name="Change Lead the Checkout API"s]')
        ->assertValue('#goal-title', 'Lead the Checkout API')
        ->assertSee('What achieving it looks like')
        ->assertSee('Say what done looks like by the end of the year')
        ->fill('#goal-title', '   ')
        ->click('Save Goal')
        ->assertSee('The title field is required.')
        ->assertVisible('#goal-title')
        ->fill('#goal-title', 'Lead the Payments API')
        ->click('Cancel')
        ->assertMissing('#goal-title')
        ->assertSee('Lead the Checkout API')
        ->assertSee('Own its design.')
        ->click('internal:role=button[name="Edit"s]')
        ->assertValue('#goal-title', 'Lead the Checkout API')
        ->assertDontSee('The title field is required.')
        ->assertNoJavaScriptErrors();

    expect($goal->fresh()?->title)->toBe('Lead the Checkout API');
});
