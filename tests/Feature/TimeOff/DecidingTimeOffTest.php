<?php

use App\Enums\TimeOffState;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\TimeOffApprovalController;
use App\Http\Controllers\TimeOffController;
use App\Http\Controllers\TimeOffDeclineController;
use App\Http\Requests\TimeOffApprovalRequest;
use App\Http\Requests\TimeOffDeclineRequest;
use App\Models\Employee;
use App\Models\PaidDays;
use App\Models\Role;
use App\Models\TimeOff;
use App\Policies\TimeOffPolicy;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(ApprovalController::class, TimeOffApprovalController::class, TimeOffController::class, TimeOffApprovalRequest::class, TimeOffDeclineController::class, TimeOffDeclineRequest::class, TimeOffPolicy::class, TimeOff::class);

beforeEach(function () {
    travelTo('2026-12-01 09:00');
});

/**
 * Ben, below Ada at the top, who is Cara's Manager.
 */
function timeOffManager(): Employee
{
    $ada = Employee::factory()->top()->create(['name' => 'Ada']);

    return Employee::factory()->create(['name' => 'Ben', 'manager_id' => $ada->id]);
}

/**
 * Unpaid Time Off from Mon 14 to Wed 16 Dec 2026 for Cara, who reports to
 * the given Manager, Pending unless given another factory state.
 */
function pendingTimeOffOf(Employee $manager, ?string $state = null): TimeOff
{
    $factory = TimeOff::factory()->unpaid()->for(Employee::factory()->create(['name' => 'Cara', 'start_date' => '2025-01-06', 'manager_id' => $manager->id]));

    return ($state === null ? $factory : $factory->{$state}())->create(['first_date' => '2026-12-14', 'last_date' => '2026-12-16']);
}

it('lets the Manager approve their Direct Report\'s Pending Time Off, recording who and when', function () {
    $ben = timeOffManager();
    $timeOff = pendingTimeOffOf($ben);

    actingAs($ben)->post(route('time-off.approval.store', $timeOff))
        ->assertRedirect(route('approvals.index', ['tab' => 'time-off']))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Time Off approved.']);

    $timeOff->refresh();
    expect($timeOff->state)->toBe(TimeOffState::Approved);
    expect($timeOff->decided_by)->toBe($ben->id);
    expect($timeOff->decided_at)->toEqual(now());
});

it('lets the Manager decline their Direct Report\'s Pending Time Off with a reason, recording who, when and why', function () {
    $ben = timeOffManager();
    $timeOff = pendingTimeOffOf($ben);

    actingAs($ben)->post(route('time-off.decline.store', $timeOff), ['decline_reason' => 'The release is that week.'])
        ->assertRedirect(route('approvals.index', ['tab' => 'time-off']))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Time Off declined.']);

    $timeOff->refresh();
    expect($timeOff->state)->toBe(TimeOffState::Declined);
    expect($timeOff->decided_by)->toBe($ben->id);
    expect($timeOff->decided_at)->toEqual(now());
    expect($timeOff->decline_reason)->toBe('The release is that week.');
});

it('refuses a decline without a reason', function (?string $reason) {
    $ben = timeOffManager();
    $timeOff = pendingTimeOffOf($ben);

    actingAs($ben)->post(route('time-off.decline.store', $timeOff), ['decline_reason' => $reason])
        ->assertSessionHasErrors(['decline_reason' => 'Give a reason for declining.']);

    expect($timeOff->refresh()->state)->toBe(TimeOffState::Pending);
})->with(['missing' => null, 'blank' => '   ']);

it('refuses a decline reason that isn\'t text', function () {
    $ben = timeOffManager();
    $timeOff = pendingTimeOffOf($ben);

    actingAs($ben)->post(route('time-off.decline.store', $timeOff), ['decline_reason' => ['The release is that week.']])
        ->assertSessionHasErrors('decline_reason');

    expect($timeOff->refresh()->state)->toBe(TimeOffState::Pending);
});

dataset('answers', [
    'approve' => ['time-off.approval.store', []],
    'decline' => ['time-off.decline.store', ['decline_reason' => 'The release is that week.']],
]);

it('refuses answering Time Off to anyone but the Employee\'s Manager, even with the Admin Role', function (string $route, array $form) {
    $ben = timeOffManager();
    $timeOff = pendingTimeOffOf($ben);
    $admin = Employee::factory()->create(['manager_id' => $ben->manager_id]);
    $admin->roles()->attach(Role::factory()->admin()->create());
    $ada = Employee::query()->findOrFail($ben->manager_id);
    $carasReport = Employee::factory()->create(['manager_id' => $timeOff->employee_id]);

    foreach ([$admin, $ada, $carasReport, $timeOff->employee] as $outsider) {
        actingAs($outsider)->post(route($route, $timeOff), $form)->assertForbidden();
    }

    expect($timeOff->refresh()->state)->toBe(TimeOffState::Pending);
})->with('answers');

it('lets the Employee\'s current Manager answer after they move, and not the one before', function (string $route, array $form) {
    $ben = timeOffManager();
    $timeOff = pendingTimeOffOf($ben);
    $hana = Employee::factory()->create(['name' => 'Hana', 'manager_id' => $ben->manager_id]);
    $timeOff->employee->update(['manager_id' => $hana->id]);

    actingAs($ben)->post(route($route, $timeOff), $form)->assertForbidden();
    actingAs($hana)->post(route($route, $timeOff), $form)->assertRedirect(route('approvals.index', ['tab' => 'time-off']));

    expect($timeOff->refresh()->decided_by)->toBe($hana->id);
})->with('answers');

it('lets the next Manager up answer once the Employee\'s Manager has left', function (string $route, array $form) {
    $ben = timeOffManager();
    $timeOff = pendingTimeOffOf($ben);
    $ben->update(['leaving_date' => '2026-11-30']);

    actingAs(Employee::query()->findOrFail($ben->manager_id))->post(route($route, $timeOff), $form)->assertRedirect(route('approvals.index', ['tab' => 'time-off']));

    expect($timeOff->refresh()->decided_by)->toBe($ben->manager_id);
})->with('answers');

it('refuses answering Time Off that isn\'t Pending', function (string $state, string $route, array $form) {
    $ben = timeOffManager();
    $timeOff = pendingTimeOffOf($ben, $state);

    actingAs($ben)->post(route($route, $timeOff), $form)->assertForbidden();

    expect($timeOff->refresh()->state)->toBe(TimeOffState::from($state));
})->with(['approved', 'declined', 'cancelled'])->with('answers');

it('lets the Manager answer Pending Time Off whose first date has passed', function (string $route, array $form) {
    $ben = timeOffManager();
    $timeOff = pendingTimeOffOf($ben);
    travelTo('2026-12-17 09:00');

    actingAs($ben)->post(route($route, $timeOff), $form)->assertRedirect(route('approvals.index', ['tab' => 'time-off']));

    expect($timeOff->refresh()->state)->not->toBe(TimeOffState::Pending);
})->with('answers');

/**
 * Cara's Paid Time Off from Mon 28 Dec 2026 to Fri 8 Jan 2027: 4 weekdays in
 * 2026 and 6 in 2027, Pending with Ben as her Manager. She has 20 Paid Days
 * a year from 2026.
 */
function pendingPaidTimeOffOverNewYear(Employee $ben): TimeOff
{
    $cara = Employee::factory()->create(['name' => 'Cara', 'start_date' => '2025-01-06', 'manager_id' => $ben->id]);
    PaidDays::factory()->for($cara)->create(['year' => 2026, 'days' => 20]);

    return TimeOff::factory()->for($cara)->create(['first_date' => '2026-12-28', 'last_date' => '2027-01-08']);
}

it('approves Paid Time Off that takes exactly the Paid Days left', function () {
    $ben = timeOffManager();
    $timeOff = pendingPaidTimeOffOverNewYear($ben);
    PaidDays::factory()->for($timeOff->employee)->create(['year' => 2027, 'days' => 6]);
    TimeOff::factory()->for($timeOff->employee)->approved()->create(['first_date' => '2026-12-04', 'last_date' => '2026-12-25']);

    actingAs($ben)->post(route('time-off.approval.store', $timeOff))->assertSessionHasNoErrors();

    expect($timeOff->refresh()->state)->toBe(TimeOffState::Approved);
});

it('refuses approving Paid Time Off once Paid Days were lowered below what it takes, saying how many are left', function (int $year, int $days, string $message) {
    $ben = timeOffManager();
    $timeOff = pendingPaidTimeOffOverNewYear($ben);
    PaidDays::factory()->for($timeOff->employee)->create(['year' => 2027, 'days' => 20]);
    $timeOff->employee->paidDays()->where('year', $year)->update(['days' => $days]);

    actingAs($ben)->post(route('time-off.approval.store', $timeOff))
        ->assertSessionHasErrors(['approval' => $message]);

    expect($timeOff->refresh()->state)->toBe(TimeOffState::Pending);
})->with([
    'in its first year' => [2026, 3, 'Cara has 3 Paid Days left in 2026 besides this Time Off, but it takes 4. Decline it instead, so the rest can be requested as Unpaid Time Off.'],
    'in its last year' => [2027, 5, 'Cara has 5 Paid Days left in 2027 besides this Time Off, but it takes 6. Decline it instead, so the rest can be requested as Unpaid Time Off.'],
    'down to one' => [2027, 1, 'Cara has 1 Paid Day left in 2027 besides this Time Off, but it takes 6. Decline it instead, so the rest can be requested as Unpaid Time Off.'],
]);

it('approves Unpaid Time Off whatever Paid Days are left', function () {
    $ben = timeOffManager();
    $timeOff = pendingTimeOffOf($ben);

    actingAs($ben)->post(route('time-off.approval.store', $timeOff))->assertSessionHasNoErrors();

    expect($timeOff->refresh()->state)->toBe(TimeOffState::Approved);
});

it('shows the Employee who declined their Time Off and why', function () {
    $ben = timeOffManager();
    $timeOff = pendingTimeOffOf($ben);
    actingAs($ben)->post(route('time-off.decline.store', $timeOff), ['decline_reason' => 'The release is that week.']);

    actingAs($timeOff->employee)->get(route('time-off.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('timeOff.0.state', 'declined')
            ->where('timeOff.0.decided_by', 'Ben')
            ->where('timeOff.0.decline_reason', 'The release is that week.'));
});

it('lists the Pending Time Off of the Manager\'s Direct Reports on the Approvals Time Off tab, earliest first, with the Paid Days they leave', function () {
    $ben = timeOffManager();
    $newYear = pendingPaidTimeOffOverNewYear($ben);
    $cara = $newYear->employee;
    TimeOff::factory()->for($cara)->approved()->create(['first_date' => '2026-12-07', 'last_date' => '2026-12-11']);
    TimeOff::factory()->for($cara)->declined()->create(['first_date' => '2026-12-21', 'last_date' => '2026-12-21']);
    $dev = Employee::factory()->former()->create(['name' => 'Dev', 'manager_id' => $ben->id]);
    $devs = TimeOff::factory()->for($dev)->unpaid()->create(['first_date' => '2026-11-16', 'last_date' => '2026-11-17']);
    TimeOff::factory()->for(Employee::factory()->create(['manager_id' => $cara->id]))->create(['first_date' => '2026-12-14', 'last_date' => '2026-12-14']);
    TimeOff::factory()->for($ben)->create(['first_date' => '2026-12-14', 'last_date' => '2026-12-14']);

    actingAs($ben)->get(route('approvals.index', ['tab' => 'time-off']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'time-off')
            ->where('decidesTimeOff', true)
            ->where('waiting', ['workEntries' => 0, 'timeOff' => 2])
            ->where('toApprove', [
                ['id' => $devs->id, 'employee' => ['id' => $dev->id, 'name' => 'Dev'], 'first_date' => '2026-11-16', 'last_date' => '2026-11-17', 'days' => 2, 'is_paid' => false, 'paid_days_left' => [['year' => 2026, 'days' => 0]]],
                ['id' => $newYear->id, 'employee' => ['id' => $cara->id, 'name' => 'Cara'], 'first_date' => '2026-12-28', 'last_date' => '2027-01-08', 'days' => 10, 'is_paid' => true, 'paid_days_left' => [['year' => 2026, 'days' => 11], ['year' => 2027, 'days' => 14]]],
            ])
            ->missing('entries')
            ->missing('filters'));
});

it('no longer lists Time Off to approve on the Time Off page', function () {
    $ben = timeOffManager();
    pendingTimeOffOf($ben);

    actingAs($ben)->get(route('time-off.index'))
        ->assertInertia(fn (Assert $page) => $page->missing('toApprove'));
});
