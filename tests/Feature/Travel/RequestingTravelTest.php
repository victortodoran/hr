<?php

use App\Enums\TravelRequestState;
use App\Http\Controllers\Travel\TravelRequestController;
use App\Http\Requests\Travel\TravelRequestRequest;
use App\Models\Assignment;
use App\Models\Client;
use App\Models\CompanySettings;
use App\Models\Employee;
use App\Models\Project;
use App\Models\TimeOff;
use App\Models\TravelRequest;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\travelTo;

covers(TravelRequestController::class, TravelRequestRequest::class, TravelRequest::class, TravelRequestState::class);

beforeEach(function () {
    travelTo('2026-12-01 09:00');
    CompanySettings::factory()->create();
});

/**
 * Cara, who started on 6 Jan 2025 and reports to Mona.
 *
 * @param  array<string, mixed>  $attributes
 */
function traveller(array $attributes = []): Employee
{
    return Employee::factory()->startedLongAgo()->create(['name' => 'Cara', 'manager_id' => fn () => Employee::factory()->startedLongAgo()->create(['name' => 'Mona'])->id, ...$attributes]);
}

/**
 * A Travel Request as the request form sends it: Cara to Berlin from Thu 3
 * to Sat 5 Dec 2026, with some changes.
 *
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function travelForm(Assignment $assignment, array $changes = []): array
{
    return ['assignment_id' => $assignment->id, 'destination' => 'Berlin', 'first_date' => '2026-12-03', 'last_date' => '2026-12-05', 'purpose' => 'Relaunch workshop', ...$changes];
}

/**
 * The Employee's Travel Requests as stored, oldest first.
 *
 * @return array<int, array<string, mixed>>
 */
function storedTravelRequests(Employee $employee): array
{
    return TravelRequest::query()->where('employee_id', $employee->id)->orderBy('id')->get()
        ->map(fn (TravelRequest $travelRequest): array => [
            'assignment_id' => $travelRequest->assignment_id,
            'destination' => $travelRequest->destination,
            'first_date' => $travelRequest->first_date->toDateString(),
            'last_date' => $travelRequest->last_date->toDateString(),
            'purpose' => $travelRequest->purpose,
            'state' => $travelRequest->state,
        ])
        ->all();
}

it('lets an Employee request travel on their Assignment, which starts Pending and counts every day', function () {
    $cara = traveller();
    $relaunch = assignmentOf($cara);

    actingAs($cara)->post(route('travel-requests.store'), travelForm($relaunch))
        ->assertRedirect(route('travel-requests.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Travel Request made.']);

    expect(storedTravelRequests($cara))->toBe([
        ['assignment_id' => $relaunch->id, 'destination' => 'Berlin', 'first_date' => '2026-12-03', 'last_date' => '2026-12-05', 'purpose' => 'Relaunch workshop', 'state' => TravelRequestState::Pending],
    ]);
    $travelRequest = TravelRequest::query()->sole();
    expect($travelRequest->decided_by)->toBeNull();
    expect($travelRequest->decided_at)->toBeNull();

    actingAs($cara)->get(route('travel-requests.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('travelRequests.upcoming.0.id', $travelRequest->id)
            ->where('travelRequests.upcoming.0.state', 'pending'));
    actingAs($cara)->get(route('travel-requests.show', $travelRequest))
        ->assertInertia(fn (Assert $page) => $page->where('travelRequest.days', 3));
});

it('lets an Employee travel for the company itself on their Internal Assignment', function () {
    $cara = traveller();
    $internal = Assignment::factory()->internal()->for($cara)->create();

    actingAs($cara)->post(route('travel-requests.store'), travelForm($internal, ['first_date' => '2026-12-01', 'last_date' => '2026-12-01']))
        ->assertSessionHasNoErrors();

    expect(storedTravelRequests($cara))->toHaveCount(1);
});

it('approves the Travel Request of an Employee with no Manager at once, with nobody deciding', function () {
    $ana = traveller(['manager_id' => null]);

    actingAs($ana)->post(route('travel-requests.store'), travelForm(assignmentOf($ana)))->assertSessionHasNoErrors();

    $travelRequest = TravelRequest::query()->sole();
    expect($travelRequest->state)->toBe(TravelRequestState::Approved);
    expect($travelRequest->decided_by)->toBeNull();
    expect($travelRequest->decided_at)->toEqual(now());

    actingAs($ana)->get(route('travel-requests.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('travelRequests.upcoming.0.id', $travelRequest->id)
            ->where('travelRequests.upcoming.0.state', 'approved'));
});

it('refuses a Travel Request with missing or invalid fields, in the past or with the last date before the first', function (array $input, string $field, string $message) {
    $cara = traveller();

    actingAs($cara)->post(route('travel-requests.store'), travelForm(assignmentOf($cara), $input))
        ->assertSessionHasErrors([$field => $message]);

    expect(session('errors')->get($field))->toBe([$message]);
    expect(storedTravelRequests($cara))->toBe([]);
})->with([
    'yesterday' => [['first_date' => '2026-11-30'], 'first_date', 'Travel Requests can\'t start in the past.'],
    'last date before the first' => [['last_date' => '2026-12-02'], 'last_date', 'The last date field must be a date after or equal to first date.'],
    'no first date' => [['first_date' => ''], 'first_date', 'The first date field is required.'],
    'no last date' => [['last_date' => ''], 'last_date', 'The last date field is required.'],
    'first date not a date' => [['first_date' => '3 Dec 2026'], 'first_date', 'The first date field must match the format Y-m-d.'],
    'no Assignment' => [['assignment_id' => ''], 'assignment_id', 'Choose the Assignment this Travel Request is for.'],
    'Assignment not a number' => [['assignment_id' => 'relaunch'], 'assignment_id', 'Choose one of your Assignments.'],
    'no destination' => [['destination' => ''], 'destination', 'The destination field is required.'],
    'destination too long' => [['destination' => str_repeat('a', 256)], 'destination', 'The destination field must not be greater than 255 characters.'],
    'no purpose' => [['purpose' => ''], 'purpose', 'The purpose field is required.'],
    'purpose too long' => [['purpose' => str_repeat('a', 1001)], 'purpose', 'The purpose field must not be greater than 1000 characters.'],
]);

it('refuses travel on someone else\'s Assignment', function () {
    $cara = traveller();
    $someoneElses = assignmentOf(Employee::factory()->startedLongAgo()->create());

    actingAs($cara)->post(route('travel-requests.store'), travelForm($someoneElses))
        ->assertSessionHasErrors(['assignment_id' => 'Choose one of your Assignments.']);

    expect(TravelRequest::query()->count())->toBe(0);
});

it('refuses dates outside the Assignment\'s dates', function (array $assignment, array $input, string $field, string $message) {
    $cara = traveller();

    actingAs($cara)->post(route('travel-requests.store'), travelForm(assignmentOf($cara, 'Relaunch', $assignment), $input))
        ->assertSessionHasErrors([$field => $message]);

    expect(session('errors')->get($field))->toBe([$message]);
    expect(storedTravelRequests($cara))->toBe([]);
})->with([
    'before it starts' => [['start_date' => '2026-12-04'], [], 'first_date', 'Your Assignment to Relaunch starts on 4 Dec 2026, so a Travel Request can\'t start before it.'],
    'after it ends' => [['end_date' => '2026-12-04'], [], 'last_date', 'Your Assignment to Relaunch ends on 4 Dec 2026, so a Travel Request must end by then.'],
]);

it('accepts travel from the Assignment\'s first day to its last', function () {
    $cara = traveller();

    actingAs($cara)->post(route('travel-requests.store'), travelForm(assignmentOf($cara, 'Relaunch', ['start_date' => '2026-12-03', 'end_date' => '2026-12-05'])))
        ->assertSessionHasNoErrors();

    expect(storedTravelRequests($cara))->toHaveCount(1);
});

it('refuses travel sharing a day with the Employee\'s own Pending or Approved Travel Requests', function (string $state) {
    $cara = traveller();
    $relaunch = assignmentOf($cara);
    $factory = TravelRequest::factory()->on($relaunch);
    ($state === 'pending' ? $factory : $factory->approved())->create(['first_date' => '2026-12-05', 'last_date' => '2026-12-08']);

    actingAs($cara)->post(route('travel-requests.store'), travelForm($relaunch))
        ->assertSessionHasErrors(['first_date' => 'You already have a Travel Request from 5 Dec 2026 to 8 Dec 2026, so these dates can\'t overlap it.']);

    expect(TravelRequest::query()->count())->toBe(1);
})->with(['pending', 'approved']);

it('refuses travel sharing a day with the Employee\'s own Pending or Approved Time Off', function (string $state) {
    $cara = traveller();
    $factory = TimeOff::factory()->for($cara);
    ($state === 'pending' ? $factory : $factory->approved())->create(['first_date' => '2026-12-04', 'last_date' => '2026-12-04']);

    actingAs($cara)->post(route('travel-requests.store'), travelForm(assignmentOf($cara)))
        ->assertSessionHasErrors(['first_date' => 'You have Time Off from 4 Dec 2026 to 4 Dec 2026, so a Travel Request can\'t overlap it.']);

    expect(TravelRequest::query()->count())->toBe(0);
})->with(['pending', 'approved']);

it('allows travel over Declined or Cancelled Travel Requests and Time Off, someone else\'s, or right next to its own', function () {
    $cara = traveller();
    $relaunch = assignmentOf($cara);
    TravelRequest::factory()->on($relaunch)->declined()->create(['first_date' => '2026-12-03', 'last_date' => '2026-12-05']);
    TravelRequest::factory()->on($relaunch)->cancelled()->create(['first_date' => '2026-12-04', 'last_date' => '2026-12-04']);
    TravelRequest::factory()->create(['first_date' => '2026-12-03', 'last_date' => '2026-12-05']);
    TravelRequest::factory()->on($relaunch)->create(['first_date' => '2026-12-02', 'last_date' => '2026-12-02']);
    TimeOff::factory()->for($cara)->declined()->create(['first_date' => '2026-12-03', 'last_date' => '2026-12-04']);
    TimeOff::factory()->for($cara)->cancelled()->create(['first_date' => '2026-12-04', 'last_date' => '2026-12-04']);
    TimeOff::factory()->create(['first_date' => '2026-12-03', 'last_date' => '2026-12-04']);
    TimeOff::factory()->for($cara)->create(['first_date' => '2026-12-07', 'last_date' => '2026-12-07']);

    actingAs($cara)->post(route('travel-requests.store'), travelForm($relaunch))->assertSessionHasNoErrors();

    expect(storedTravelRequests($cara))->toHaveCount(4);
});

it('opens the Request Travel sheet over the Travel page, with today and the Assignments the Employee can still travel on, with their dates', function () {
    $cara = traveller();
    $globex = Client::factory()->create(['name' => 'Globex']);
    $relaunch = Assignment::factory()->for($cara)->for(Project::factory()->forClient($globex)->create(['name' => 'Relaunch', 'start_date' => '2026-01-05']))
        ->create(['start_date' => '2026-02-02', 'end_date' => null]);
    Project::factory()->internal()->create(['name' => 'Internal']);
    $internal = Assignment::factory()->internal()->for($cara)->create();
    assignmentOf($cara, 'Data Audit', ['start_date' => '2026-02-02', 'end_date' => '2026-11-30']);
    $endsToday = assignmentOf($cara, 'Archive Cleanup', ['start_date' => '2026-11-02', 'end_date' => '2026-12-01']);
    assignmentOf(Employee::factory()->create(), 'Someone Else\'s');

    actingAs($cara)->get(route('travel-requests.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('travel-requests/Index')
            ->has('travelRequests.upcoming')
            ->where('requesting', true)
            ->where('today', '2026-12-01')
            ->where('assignments', [
                ['id' => $endsToday->id, 'project' => 'Archive Cleanup', 'client' => null, 'start_date' => '2026-11-02', 'end_date' => '2026-12-01'],
                ['id' => $internal->id, 'project' => 'Internal', 'client' => null, 'start_date' => '2025-01-06', 'end_date' => null],
                ['id' => $relaunch->id, 'project' => 'Relaunch', 'client' => 'Globex', 'start_date' => '2026-02-02', 'end_date' => null],
            ]));
});

it('needs signing in to see or request travel', function () {
    get(route('travel-requests.index'))->assertRedirect(route('login'));
    get(route('travel-requests.create'))->assertRedirect(route('login'));
});
