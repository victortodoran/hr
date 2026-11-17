<?php

use App\Enums\TravelRequestState;
use App\Http\Controllers\Travel\TravelRequestApprovalController;
use App\Http\Controllers\Travel\TravelRequestController;
use App\Http\Controllers\Travel\TravelRequestDeclineController;
use App\Http\Controllers\WorkEntries\ApprovalController;
use App\Http\Requests\Travel\TravelRequestDeclineRequest;
use App\Models\Client;
use App\Models\CompanySettings;
use App\Models\Employee;
use App\Models\Role;
use App\Models\TravelRequest;
use App\Policies\TravelRequestPolicy;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(ApprovalController::class, TravelRequestApprovalController::class, TravelRequestController::class, TravelRequestDeclineController::class, TravelRequestDeclineRequest::class, TravelRequestPolicy::class);

beforeEach(function () {
    travelTo('2026-12-01 09:00');
    CompanySettings::factory()->create();
});

/**
 * Cara's Travel Request to Berlin from Mon 14 to Wed 16 Dec 2026 on
 * Relaunch, with the given Manager, Pending unless given another factory
 * state.
 */
function pendingTravelRequestOf(Employee $manager, ?string $state = null): TravelRequest
{
    $cara = Employee::factory()->startedLongAgo()->reportingTo($manager)->create(['name' => 'Cara']);
    $factory = TravelRequest::factory()->on(assignmentOf($cara));

    return ($state === null ? $factory : $factory->{$state}())->create(['destination' => 'Berlin', 'first_date' => '2026-12-14', 'last_date' => '2026-12-16']);
}

dataset('travel answers', [
    'approve' => ['travel-requests.approval.store', []],
    'decline' => ['travel-requests.decline.store', ['decline_reason' => 'The release is that week.']],
]);

it('lets the Manager approve their Report\'s Pending Travel Request, recording who and when', function () {
    $ben = managerBen();
    $travelRequest = pendingTravelRequestOf($ben);

    actingAs($ben)->post(route('travel-requests.approval.store', $travelRequest))
        ->assertRedirect(route('approvals.index', ['tab' => 'travel-requests']))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Travel Request approved.']);

    $travelRequest->refresh();
    expect($travelRequest->state)->toBe(TravelRequestState::Approved);
    expect($travelRequest->decided_by)->toBe($ben->id);
    expect($travelRequest->decided_at)->toEqual(now());
    expect($travelRequest->decline_reason)->toBeNull();
});

it('lets the Manager decline their Report\'s Pending Travel Request with a reason, recording who, when and why', function () {
    $ben = managerBen();
    $travelRequest = pendingTravelRequestOf($ben);

    actingAs($ben)->post(route('travel-requests.decline.store', $travelRequest), ['decline_reason' => 'The release is that week.'])
        ->assertRedirect(route('approvals.index', ['tab' => 'travel-requests']))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Travel Request declined.']);

    $travelRequest->refresh();
    expect($travelRequest->state)->toBe(TravelRequestState::Declined);
    expect($travelRequest->decided_by)->toBe($ben->id);
    expect($travelRequest->decided_at)->toEqual(now());
    expect($travelRequest->decline_reason)->toBe('The release is that week.');
});

it('refuses a decline without a reason', function (?string $reason) {
    $ben = managerBen();
    $travelRequest = pendingTravelRequestOf($ben);

    actingAs($ben)->post(route('travel-requests.decline.store', $travelRequest), ['decline_reason' => $reason])
        ->assertSessionHasErrors(['decline_reason' => 'Give a reason for declining.']);

    expect($travelRequest->refresh()->state)->toBe(TravelRequestState::Pending);
})->with(['missing' => null, 'blank' => '   ']);

it('refuses a decline reason that isn\'t text', function () {
    $ben = managerBen();
    $travelRequest = pendingTravelRequestOf($ben);

    actingAs($ben)->post(route('travel-requests.decline.store', $travelRequest), ['decline_reason' => ['The release is that week.']])
        ->assertSessionHasErrors('decline_reason');

    expect($travelRequest->refresh()->state)->toBe(TravelRequestState::Pending);
});

it('refuses answering a Travel Request to anyone but the Employee\'s Manager, even with the Admin Role', function (string $route, array $form) {
    $ben = managerBen();
    $travelRequest = pendingTravelRequestOf($ben);
    $admin = Employee::factory()->create(['manager_id' => $ben->manager_id]);
    $admin->roles()->attach(Role::factory()->admin()->create());
    $ada = Employee::query()->findOrFail($ben->manager_id);
    $carasReport = Employee::factory()->create(['manager_id' => $travelRequest->employee_id]);

    foreach ([$admin, $ada, $carasReport, $travelRequest->employee] as $outsider) {
        actingAs($outsider)->post(route($route, $travelRequest), $form)->assertForbidden();
    }

    expect($travelRequest->refresh()->state)->toBe(TravelRequestState::Pending);
})->with('travel answers');

it('lets the Employee\'s current Manager answer after they move, and not the one before', function (string $route, array $form) {
    $ben = managerBen();
    $travelRequest = pendingTravelRequestOf($ben);
    $hana = Employee::factory()->create(['name' => 'Hana', 'manager_id' => $ben->manager_id]);
    $travelRequest->employee->update(['manager_id' => $hana->id]);

    actingAs($ben)->post(route($route, $travelRequest), $form)->assertForbidden();
    actingAs($hana)->post(route($route, $travelRequest), $form)->assertRedirect(route('approvals.index', ['tab' => 'travel-requests']));

    expect($travelRequest->refresh()->decided_by)->toBe($hana->id);
})->with('travel answers');

it('lets the next Manager up answer once the Employee\'s Manager has left', function (string $route, array $form) {
    $ben = managerBen();
    $travelRequest = pendingTravelRequestOf($ben);
    $ben->update(['leaving_date' => '2026-11-30']);

    actingAs(Employee::query()->findOrFail($ben->manager_id))->post(route($route, $travelRequest), $form)->assertRedirect(route('approvals.index', ['tab' => 'travel-requests']));

    expect($travelRequest->refresh()->decided_by)->toBe($ben->manager_id);
})->with('travel answers');

it('refuses answering a Travel Request that isn\'t Pending', function (string $state, string $route, array $form) {
    $ben = managerBen();
    $travelRequest = pendingTravelRequestOf($ben, $state);

    actingAs($ben)->post(route($route, $travelRequest), $form)->assertForbidden();

    expect($travelRequest->refresh()->state)->toBe(TravelRequestState::from($state));
})->with(['approved', 'declined', 'cancelled'])->with('travel answers');

it('lets the Manager answer a Pending Travel Request whose first date has passed', function (string $route, array $form) {
    $ben = managerBen();
    $travelRequest = pendingTravelRequestOf($ben);
    travelTo('2026-12-17 09:00');

    actingAs($ben)->post(route($route, $travelRequest), $form)->assertRedirect(route('approvals.index', ['tab' => 'travel-requests']));

    expect($travelRequest->refresh()->state)->not->toBe(TravelRequestState::Pending);
})->with('travel answers');

it('shows the Employee who declined their Travel Request and why', function () {
    $ben = managerBen();
    $travelRequest = pendingTravelRequestOf($ben);
    actingAs($ben)->post(route('travel-requests.decline.store', $travelRequest), ['decline_reason' => 'The release is that week.']);

    actingAs($travelRequest->employee)->get(route('travel-requests.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('travelRequests.past.0.id', $travelRequest->id)
            ->where('travelRequests.past.0.state', 'declined'));
    actingAs($travelRequest->employee)->get(route('travel-requests.show', $travelRequest))
        ->assertInertia(fn (Assert $page) => $page
            ->where('travelRequest.state', 'declined')
            ->where('travelRequest.decided_by', 'Ben')
            ->where('travelRequest.decline_reason', 'The release is that week.'));
});

it('lists the Pending Travel Requests of the Employees the Manager effectively manages on the Approvals Travel Requests tab, earliest first', function () {
    $ben = managerBen();
    $berlin = pendingTravelRequestOf($ben);
    $cara = $berlin->employee;
    $relaunch = $berlin->assignment;
    $relaunch->project->client()->associate(Client::factory()->create(['name' => 'Globex']))->save();
    TravelRequest::factory()->on($relaunch)->approved()->create(['first_date' => '2026-12-07', 'last_date' => '2026-12-08']);
    TravelRequest::factory()->on($relaunch)->declined()->create(['first_date' => '2026-12-21', 'last_date' => '2026-12-21']);
    TravelRequest::factory()->on($relaunch)->cancelled()->create(['first_date' => '2026-12-22', 'last_date' => '2026-12-22']);
    $dev = Employee::factory()->former()->reportingTo($ben)->create(['name' => 'Dev']);
    $devs = TravelRequest::factory()->on(assignmentOf($dev, 'Website'))->create(['destination' => 'Lisbon', 'first_date' => '2026-11-16', 'last_date' => '2026-11-16', 'purpose' => 'Design conference']);
    TravelRequest::factory()->on(assignmentOf(Employee::factory()->reportingTo($cara)->create()))->create();
    TravelRequest::factory()->on(assignmentOf($ben))->create();

    actingAs($ben)->get(route('approvals.index', ['tab' => 'travel-requests']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'travel-requests')
            ->where('waiting', ['workEntries' => 0, 'timeOff' => 0, 'travelRequests' => 2, 'travelExpenses' => 0])
            ->where('travelRequestsToApprove', [
                ['id' => $devs->id, 'employee' => ['id' => $dev->id, 'name' => 'Dev'], 'project' => 'Website', 'client' => null, 'destination' => 'Lisbon', 'first_date' => '2026-11-16', 'last_date' => '2026-11-16', 'days' => 1, 'purpose' => 'Design conference'],
                ['id' => $berlin->id, 'employee' => ['id' => $cara->id, 'name' => 'Cara'], 'project' => 'Relaunch', 'client' => 'Globex', 'destination' => 'Berlin', 'first_date' => '2026-12-14', 'last_date' => '2026-12-16', 'days' => 3, 'purpose' => $berlin->purpose],
            ])
            ->missing('toApprove')
            ->missing('entries'));
});
