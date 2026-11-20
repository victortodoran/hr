<?php

use App\Actions\TimeOff\ApproveTimeOff;
use App\Actions\TimeOff\DeclineTimeOff;
use App\Enums\TimeOffState;
use App\Http\Controllers\TimeOff\TimeOffApprovalController;
use App\Http\Controllers\TimeOff\TimeOffController;
use App\Http\Controllers\TimeOff\TimeOffDeclineController;
use App\Http\Controllers\WorkEntries\ApprovalController;
use App\Http\Requests\TimeOff\TimeOffApprovalRequest;
use App\Http\Requests\TimeOff\TimeOffDeclineRequest;
use App\Models\Employee;
use App\Models\Role;
use App\Models\TimeOff;
use App\Policies\TimeOffPolicy;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(ApproveTimeOff::class, DeclineTimeOff::class, ApprovalController::class, TimeOffApprovalController::class, TimeOffController::class, TimeOffApprovalRequest::class, TimeOffDeclineController::class, TimeOffDeclineRequest::class, TimeOffPolicy::class, TimeOff::class);

beforeEach(function () {
    travelTo('2026-12-01 09:00');
});

/**
 * Unpaid Time Off from Mon 14 to Wed 16 Dec 2026 for Cara, who reports to
 * the given Manager, Pending unless given another factory state.
 */
function pendingTimeOffOf(Employee $manager, ?string $state = null): TimeOff
{
    $factory = TimeOff::factory()->unpaid()->for(Employee::factory()->startedLongAgo()->reportingTo($manager)->create(['name' => 'Cara']));

    return ($state === null ? $factory : $factory->{$state}())->create(['first_date' => '2026-12-14', 'last_date' => '2026-12-16']);
}

it('lets the Manager approve their Direct Report\'s Pending Time Off, recording who and when', function () {
    $ben = managerBen();
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
    $ben = managerBen();
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
    $ben = managerBen();
    $timeOff = pendingTimeOffOf($ben);

    actingAs($ben)->post(route('time-off.decline.store', $timeOff), ['decline_reason' => $reason])
        ->assertSessionHasErrors(['decline_reason' => 'Give a reason for declining.']);

    expect($timeOff->refresh()->state)->toBe(TimeOffState::Pending);
})->with(['missing' => null, 'blank' => '   ']);

it('refuses a decline reason that isn\'t text', function () {
    $ben = managerBen();
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
    $ben = managerBen();
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
    $ben = managerBen();
    $timeOff = pendingTimeOffOf($ben);
    $hana = Employee::factory()->create(['name' => 'Hana', 'manager_id' => $ben->manager_id]);
    $timeOff->employee->update(['manager_id' => $hana->id]);

    actingAs($ben)->post(route($route, $timeOff), $form)->assertForbidden();
    actingAs($hana)->post(route($route, $timeOff), $form)->assertRedirect(route('approvals.index', ['tab' => 'time-off']));

    expect($timeOff->refresh()->decided_by)->toBe($hana->id);
})->with('answers');

it('lets the next Manager up answer once the Employee\'s Manager has left', function (string $route, array $form) {
    $ben = managerBen();
    $timeOff = pendingTimeOffOf($ben);
    $ben->update(['leaving_date' => '2026-11-30']);

    actingAs(Employee::query()->findOrFail($ben->manager_id))->post(route($route, $timeOff), $form)->assertRedirect(route('approvals.index', ['tab' => 'time-off']));

    expect($timeOff->refresh()->decided_by)->toBe($ben->manager_id);
})->with('answers');

it('refuses answering Time Off that isn\'t Pending', function (string $state, string $route, array $form) {
    $ben = managerBen();
    $timeOff = pendingTimeOffOf($ben, $state);

    actingAs($ben)->post(route($route, $timeOff), $form)->assertForbidden();

    expect($timeOff->refresh()->state)->toBe(TimeOffState::from($state));
})->with(['approved', 'declined', 'cancelled'])->with('answers');

it('leaves Time Off as it is when it stops being Pending between the policy check and the approval', function () {
    $ben = managerBen();
    $timeOff = pendingTimeOffOf($ben);
    TimeOff::query()->whereKey($timeOff->id)->update(['state' => TimeOffState::Cancelled, 'cancelled_at' => now()]);

    expect(app(ApproveTimeOff::class)->approve($ben, $timeOff))->toBeFalse();

    $timeOff->refresh();
    expect($timeOff->state)->toBe(TimeOffState::Cancelled);
    expect($timeOff->decided_by)->toBeNull();
});

it('leaves Time Off as it is when it stops being Pending between the policy check and the decline', function () {
    $ben = managerBen();
    $timeOff = pendingTimeOffOf($ben);
    TimeOff::query()->whereKey($timeOff->id)->update(['state' => TimeOffState::Approved, 'decided_by' => $ben->id, 'decided_at' => now()]);

    expect(app(DeclineTimeOff::class)->decline($ben, $timeOff, 'The release is that week.'))->toBeFalse();

    $timeOff->refresh();
    expect($timeOff->state)->toBe(TimeOffState::Approved);
    expect($timeOff->decline_reason)->toBeNull();
});

it('lets the Manager answer Pending Time Off whose first date has passed', function (string $route, array $form) {
    $ben = managerBen();
    $timeOff = pendingTimeOffOf($ben);
    travelTo('2026-12-17 09:00');

    actingAs($ben)->post(route($route, $timeOff), $form)->assertRedirect(route('approvals.index', ['tab' => 'time-off']));

    expect($timeOff->refresh()->state)->not->toBe(TimeOffState::Pending);
})->with('answers');

/**
 * Cara's Paid Time Off from Mon 28 Dec 2026 to Fri 8 Jan 2027: 4 weekdays in
 * 2026 and 6 in 2027, Pending with Ben as her Manager. She has 20 Paid Days
 * a year unless given others.
 */
function pendingPaidTimeOffOverNewYear(Employee $ben, int $paidDays = 20): TimeOff
{
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara', 'paid_days' => $paidDays]);

    return TimeOff::factory()->for($cara)->create(['first_date' => '2026-12-28', 'last_date' => '2027-01-08']);
}

it('approves Paid Time Off that takes exactly the Paid Days left', function () {
    $ben = managerBen();
    $timeOff = pendingPaidTimeOffOverNewYear($ben);
    TimeOff::factory()->for($timeOff->employee)->approved()->create(['first_date' => '2026-12-04', 'last_date' => '2026-12-25']);
    TimeOff::factory()->for($timeOff->employee)->approved()->create(['first_date' => '2027-02-01', 'last_date' => '2027-02-18']);

    actingAs($ben)->post(route('time-off.approval.store', $timeOff))->assertSessionHasNoErrors();

    expect($timeOff->refresh()->state)->toBe(TimeOffState::Approved);
});

it('refuses approving Paid Time Off once Paid Days left fall below what it takes, saying how many are left', function (int $paidDays, ?array $taken, string $message) {
    $ben = managerBen();
    $timeOff = pendingPaidTimeOffOverNewYear($ben, $paidDays);
    if ($taken !== null) {
        TimeOff::factory()->for($timeOff->employee)->approved()->create(['first_date' => $taken[0], 'last_date' => $taken[1]]);
    }

    actingAs($ben)->post(route('time-off.approval.store', $timeOff))
        ->assertSessionHasErrors(['approval' => $message]);

    expect($timeOff->refresh()->state)->toBe(TimeOffState::Pending);
})->with([
    'in its first year, 17 taken' => [20, ['2026-11-02', '2026-11-24'], 'Cara has 3 Paid Days left in 2026 besides this Time Off, but it takes 4. Decline it instead, so the rest can be requested as Unpaid Time Off.'],
    'in its last year, lowered to 5' => [5, null, 'Cara has 5 Paid Days left in 2027 besides this Time Off, but it takes 6. Decline it instead, so the rest can be requested as Unpaid Time Off.'],
    'down to one, 19 taken' => [20, ['2027-02-01', '2027-02-25'], 'Cara has 1 Paid Day left in 2027 besides this Time Off, but it takes 6. Decline it instead, so the rest can be requested as Unpaid Time Off.'],
    'below none, 22 taken' => [20, ['2026-11-02', '2026-12-01'], 'Cara has no Paid Days left in 2026 besides this Time Off, since 2 more are already taken than they have. Decline it instead, so it can be requested as Unpaid Time Off.'],
]);

it('approves Unpaid Time Off whatever Paid Days are left', function () {
    $ben = managerBen();
    $timeOff = pendingTimeOffOf($ben);

    actingAs($ben)->post(route('time-off.approval.store', $timeOff))->assertSessionHasNoErrors();

    expect($timeOff->refresh()->state)->toBe(TimeOffState::Approved);
});

it('shows the Employee who declined their Time Off and why', function () {
    $ben = managerBen();
    $timeOff = pendingTimeOffOf($ben);
    actingAs($ben)->post(route('time-off.decline.store', $timeOff), ['decline_reason' => 'The release is that week.']);

    actingAs($timeOff->employee)->get(route('time-off.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('timeOff.0.state', 'declined')
            ->where('timeOff.0.decided_by', 'Ben')
            ->where('timeOff.0.decline_reason', 'The release is that week.'));
});

it('lists the Pending Time Off of the Manager\'s Direct Reports on the Approvals Time Off tab, earliest first, with the Paid Days they leave', function () {
    $ben = managerBen();
    $newYear = pendingPaidTimeOffOverNewYear($ben);
    $cara = $newYear->employee;
    TimeOff::factory()->for($cara)->approved()->create(['first_date' => '2026-12-07', 'last_date' => '2026-12-11']);
    TimeOff::factory()->for($cara)->declined()->create(['first_date' => '2026-12-21', 'last_date' => '2026-12-21']);
    $dev = Employee::factory()->former()->reportingTo($ben)->create(['name' => 'Dev']);
    $devs = TimeOff::factory()->for($dev)->unpaid()->create(['first_date' => '2026-11-16', 'last_date' => '2026-11-17']);
    TimeOff::factory()->for(Employee::factory()->reportingTo($cara)->create())->create(['first_date' => '2026-12-14', 'last_date' => '2026-12-14']);
    TimeOff::factory()->for($ben)->create(['first_date' => '2026-12-14', 'last_date' => '2026-12-14']);

    actingAs($ben)->get(route('approvals.index', ['tab' => 'time-off']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'time-off')
            ->where('waiting', ['workEntries' => 0, 'timeOff' => 2, 'travelRequests' => 0, 'travelExpenses' => 0])
            ->where('toApprove', [
                ['id' => $devs->id, 'employee' => ['id' => $dev->id, 'name' => 'Dev'], 'first_date' => '2026-11-16', 'last_date' => '2026-11-17', 'days' => 2, 'is_paid' => false, 'submitted_on' => now()->toDateString(), 'paid_days_left' => [['year' => 2026, 'days' => 0]]],
                ['id' => $newYear->id, 'employee' => ['id' => $cara->id, 'name' => 'Cara'], 'first_date' => '2026-12-28', 'last_date' => '2027-01-08', 'days' => 10, 'is_paid' => true, 'submitted_on' => now()->toDateString(), 'paid_days_left' => [['year' => 2026, 'days' => 11], ['year' => 2027, 'days' => 14]]],
            ])
            ->missing('entries')
            ->missing('filters'));
});
