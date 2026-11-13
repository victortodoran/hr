<?php

use App\Enums\TimeOffState;
use App\Http\Controllers\TimeOffController;
use App\Http\Requests\TimeOffRequest;
use App\Models\Employee;
use App\Models\TimeOff;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\travelTo;

covers(TimeOffController::class, TimeOffRequest::class, TimeOff::class, TimeOffState::class, Employee::class);

beforeEach(function () {
    travelTo('2026-12-01 09:00');
});

/**
 * Cara, who started on 6 Jan 2025 and has 20 Paid Days a year.
 *
 * @param  array<string, mixed>  $attributes
 */
function timeOffRequester(array $attributes = []): Employee
{
    return Employee::factory()->create(['name' => 'Cara', 'start_date' => '2025-01-06', 'paid_days' => 20, ...$attributes]);
}

/**
 * Time Off as the request form sends it, with some changes.
 *
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function timeOffForm(array $changes = []): array
{
    return ['first_date' => '2026-12-14', 'last_date' => '2026-12-16', 'is_paid' => true, ...$changes];
}

/**
 * The Employee's Time Off as stored, oldest request first.
 *
 * @return array<int, array<string, mixed>>
 */
function storedTimeOff(Employee $employee): array
{
    return TimeOff::query()->where('employee_id', $employee->id)->orderBy('id')->get()
        ->map(fn (TimeOff $timeOff): array => [
            'first_date' => $timeOff->first_date->toDateString(),
            'last_date' => $timeOff->last_date->toDateString(),
            'is_paid' => $timeOff->is_paid,
            'state' => $timeOff->state,
        ])
        ->all();
}

it('lets an Employee request Paid Time Off, which starts Pending', function () {
    $cara = timeOffRequester();

    actingAs($cara)->post(route('time-off.store'), timeOffForm())
        ->assertRedirect(route('time-off.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Time Off requested.']);

    expect(storedTimeOff($cara))->toBe([
        ['first_date' => '2026-12-14', 'last_date' => '2026-12-16', 'is_paid' => true, 'state' => TimeOffState::Pending],
    ]);
    $timeOff = TimeOff::query()->firstOrFail();
    expect($timeOff->decided_by)->toBeNull();
    expect($timeOff->decided_at)->toBeNull();
});

it('lets an Employee with no Paid Days at all request Unpaid Time Off, a single day long', function () {
    $dev = Employee::factory()->create(['start_date' => '2025-01-06']);

    actingAs($dev)->post(route('time-off.store'), timeOffForm(['first_date' => '2026-12-01', 'last_date' => '2026-12-01', 'is_paid' => false]))
        ->assertSessionHasNoErrors();

    expect(storedTimeOff($dev))->toBe([
        ['first_date' => '2026-12-01', 'last_date' => '2026-12-01', 'is_paid' => false, 'state' => TimeOffState::Pending],
    ]);
});

it('approves the top Employee\'s Time Off at once, with nobody deciding', function () {
    $ana = timeOffRequester(['is_top' => true]);

    actingAs($ana)->post(route('time-off.store'), timeOffForm())->assertSessionHasNoErrors();

    $timeOff = TimeOff::query()->firstOrFail();
    expect($timeOff->state)->toBe(TimeOffState::Approved);
    expect($timeOff->decided_by)->toBeNull();
    expect($timeOff->decided_at)->toEqual(now());
});

it('counts only weekdays: Fri 18 to Mon 21 Dec is 2 days', function () {
    $cara = timeOffRequester();

    actingAs($cara)->post(route('time-off.store'), timeOffForm(['first_date' => '2026-12-18', 'last_date' => '2026-12-21']))
        ->assertSessionHasNoErrors();

    actingAs($cara)->get(route('time-off.index'))
        ->assertInertia(fn (Assert $page) => $page->where('timeOff.0.days', 2));
});

it('refuses Time Off with no weekdays in it', function () {
    $cara = timeOffRequester();

    actingAs($cara)->post(route('time-off.store'), timeOffForm(['first_date' => '2026-12-19', 'last_date' => '2026-12-20']))
        ->assertSessionHasErrors(['first_date' => 'Time Off must include at least one weekday; weekends don\'t count.']);

    expect(storedTimeOff($cara))->toBe([]);
});

it('refuses dates in the past, after the Leaving Date or out of order', function (array $attributes, array $input, string $field, string $message) {
    $cara = timeOffRequester($attributes);

    actingAs($cara)->post(route('time-off.store'), timeOffForm($input))
        ->assertSessionHasErrors([$field => $message]);

    expect(session('errors')->get($field))->toBe([$message]);
    expect(storedTimeOff($cara))->toBe([]);
})->with([
    'yesterday' => [[], ['first_date' => '2026-11-30'], 'first_date', 'Time Off can\'t start in the past.'],
    'after the Leaving Date' => [['leaving_date' => '2026-12-15'], [], 'last_date', 'Your Leaving Date is 15 Dec 2026, so Time Off must end by then.'],
    'last date before the first' => [[], ['last_date' => '2026-12-11'], 'last_date', 'The last date field must be a date after or equal to first date.'],
    'no first date' => [[], ['first_date' => ''], 'first_date', 'The first date field is required.'],
    'no last date' => [[], ['last_date' => ''], 'last_date', 'The last date field is required.'],
    'first date not a date' => [[], ['first_date' => '14 Dec 2026'], 'first_date', 'The first date field must match the format Y-m-d.'],
    'last date not a date' => [[], ['last_date' => 'soon'], 'last_date', 'The last date field must match the format Y-m-d.'],
    'neither Paid nor Unpaid' => [[], ['is_paid' => ''], 'is_paid', 'Choose Paid or Unpaid Time Off.'],
    'not Paid or Unpaid' => [[], ['is_paid' => 'maybe'], 'is_paid', 'Choose Paid or Unpaid Time Off.'],
]);

it('never lets Time Off start before the Start Date, since a Future Employee can\'t sign in to request it', function () {
    $cara = timeOffRequester(['start_date' => '2026-12-07']);

    actingAs($cara)->post(route('time-off.store'), timeOffForm(['first_date' => '2026-12-04', 'last_date' => '2026-12-07']))
        ->assertRedirect(route('login'));

    expect(storedTimeOff($cara))->toBe([]);
});

it('accepts Time Off from the Start Date up to the Leaving Date', function () {
    $cara = timeOffRequester(['start_date' => '2026-12-01', 'leaving_date' => '2026-12-16']);

    actingAs($cara)->post(route('time-off.store'), timeOffForm(['first_date' => '2026-12-01']))->assertSessionHasNoErrors();

    expect(storedTimeOff($cara))->toHaveCount(1);
});

it('refuses Time Off overlapping the Employee\'s own Pending or Approved Time Off', function (string $state) {
    $cara = timeOffRequester();
    TimeOff::factory()->for($cara)->unpaid()->{$state}()->create(['first_date' => '2026-12-16', 'last_date' => '2026-12-18']);

    actingAs($cara)->post(route('time-off.store'), timeOffForm())
        ->assertSessionHasErrors(['first_date' => 'You already have Time Off from 16 Dec 2026 to 18 Dec 2026, so these dates can\'t overlap it.']);

    expect(TimeOff::query()->count())->toBe(1);
})->with(['pending' => 'unpaid', 'approved' => 'approved']);

it('allows Time Off overlapping Declined or Cancelled Time Off, someone else\'s, or right next to it', function () {
    $cara = timeOffRequester();
    TimeOff::factory()->for($cara)->declined()->create(['first_date' => '2026-12-14', 'last_date' => '2026-12-16']);
    TimeOff::factory()->for($cara)->cancelled()->create(['first_date' => '2026-12-15', 'last_date' => '2026-12-15']);
    TimeOff::factory()->create(['first_date' => '2026-12-14', 'last_date' => '2026-12-16']);
    TimeOff::factory()->for($cara)->create(['first_date' => '2026-12-10', 'last_date' => '2026-12-11']);
    TimeOff::factory()->for($cara)->create(['first_date' => '2026-12-17', 'last_date' => '2026-12-17']);

    actingAs($cara)->post(route('time-off.store'), timeOffForm())->assertSessionHasNoErrors();

    expect(storedTimeOff($cara))->toHaveCount(5);
});

it('refuses Paid Time Off beyond the Paid Days left, counting Pending and Approved Paid Time Off', function () {
    $cara = timeOffRequester();
    TimeOff::factory()->for($cara)->approved()->create(['first_date' => '2026-02-02', 'last_date' => '2026-02-24']);
    TimeOff::factory()->for($cara)->create(['first_date' => '2026-12-07', 'last_date' => '2026-12-08']);

    actingAs($cara)->post(route('time-off.store'), timeOffForm())
        ->assertSessionHasErrors(['is_paid' => 'You have 1 Paid Day left in 2026, but this Paid Time Off takes 3. Request the rest as Unpaid Time Off.']);

    expect(TimeOff::query()->count())->toBe(2);
});

it('tells how many Paid Days are left: 20 Paid Days, 19 used and 3 requested Paid is refused, but accepted Unpaid', function () {
    $cara = timeOffRequester();
    TimeOff::factory()->for($cara)->approved()->create(['first_date' => '2026-02-02', 'last_date' => '2026-02-26']);

    actingAs($cara)->post(route('time-off.store'), timeOffForm())
        ->assertSessionHasErrors(['is_paid' => 'You have 1 Paid Day left in 2026, but this Paid Time Off takes 3. Request the rest as Unpaid Time Off.']);
    actingAs($cara)->post(route('time-off.store'), timeOffForm(['is_paid' => false]))->assertSessionHasNoErrors();

    expect(storedTimeOff($cara)[1])->toBe(['first_date' => '2026-12-14', 'last_date' => '2026-12-16', 'is_paid' => false, 'state' => TimeOffState::Pending]);
});

it('accepts Paid Time Off using exactly the Paid Days left, not counting Unpaid, Declined or Cancelled Time Off', function () {
    $cara = timeOffRequester();
    TimeOff::factory()->for($cara)->approved()->create(['first_date' => '2026-02-02', 'last_date' => '2026-02-24']);
    TimeOff::factory()->for($cara)->unpaid()->create(['first_date' => '2026-12-07', 'last_date' => '2026-12-11']);
    TimeOff::factory()->for($cara)->declined()->create(['first_date' => '2026-12-21', 'last_date' => '2026-12-23']);
    TimeOff::factory()->for($cara)->cancelled()->create(['first_date' => '2026-12-28', 'last_date' => '2026-12-30']);
    TimeOff::factory()->approved()->create(['first_date' => '2026-03-02', 'last_date' => '2026-03-31']);

    actingAs($cara)->post(route('time-off.store'), timeOffForm())->assertSessionHasNoErrors();

    expect(storedTimeOff($cara))->toHaveCount(5);
});

it('checks Time Off across New Year against each year\'s own days left from the same Paid Days: 28 Dec 2026 to 8 Jan 2027 is 4 days in 2026 and 6 in 2027', function (string $taken2026, string $taken2027, ?string $message) {
    $cara = Employee::factory()->create(['start_date' => '2025-01-06', 'paid_days' => 10]);
    TimeOff::factory()->for($cara)->approved()->create(['first_date' => '2026-02-02', 'last_date' => $taken2026]);
    TimeOff::factory()->for($cara)->approved()->create(['first_date' => '2027-02-01', 'last_date' => $taken2027]);

    $response = actingAs($cara)->post(route('time-off.store'), timeOffForm(['first_date' => '2026-12-28', 'last_date' => '2027-01-08']));

    if ($message === null) {
        $response->assertSessionHasNoErrors();
        expect(storedTimeOff($cara))->toHaveCount(3);
    } else {
        $response->assertSessionHasErrors(['is_paid' => $message]);
        expect(storedTimeOff($cara))->toHaveCount(2);
    }
})->with([
    '6 taken in 2026 and 4 in 2027, leaving exactly enough' => ['2026-02-09', '2027-02-04', null],
    '7 taken in 2026, leaving too few' => ['2026-02-10', '2027-02-04', 'You have 3 Paid Days left in 2026, but this Paid Time Off takes 4. Request the rest as Unpaid Time Off.'],
    '5 taken in 2027, leaving too few' => ['2026-02-09', '2027-02-05', 'You have 5 Paid Days left in 2027, but this Paid Time Off takes 6. Request the rest as Unpaid Time Off.'],
]);

it('counts Paid Time Off on the last and first day of a year in that year only', function () {
    $cara = Employee::factory()->create(['start_date' => '2025-01-06', 'paid_days' => 1]);
    TimeOff::factory()->for($cara)->approved()->create(['first_date' => '2026-12-31', 'last_date' => '2026-12-31']);
    TimeOff::factory()->for($cara)->create(['first_date' => '2027-01-01', 'last_date' => '2027-01-01']);

    actingAs($cara)->post(route('time-off.store'), timeOffForm(['first_date' => '2026-12-30', 'last_date' => '2026-12-30']))
        ->assertSessionHasErrors(['is_paid' => 'You have 0 Paid Days left in 2026, but this Paid Time Off takes 1. Request the rest as Unpaid Time Off.']);
    actingAs($cara)->post(route('time-off.store'), timeOffForm(['first_date' => '2027-01-04', 'last_date' => '2027-01-04']))
        ->assertSessionHasErrors(['is_paid' => 'You have 0 Paid Days left in 2027, but this Paid Time Off takes 1. Request the rest as Unpaid Time Off.']);
});

it('shows the Employee their own Time Off, latest first, and the Paid Days left this year', function () {
    $cara = timeOffRequester();
    $february = TimeOff::factory()->for($cara)->approved(Employee::factory()->create(['name' => 'Ben']))->create(['first_date' => '2026-02-02', 'last_date' => '2026-02-06']);
    $unpaid = TimeOff::factory()->for($cara)->unpaid()->create(['first_date' => '2026-12-14', 'last_date' => '2026-12-14']);
    $newYear = TimeOff::factory()->for($cara)->create(['first_date' => '2026-12-28', 'last_date' => '2027-01-08']);
    $cancelled = TimeOff::factory()->for($cara)->cancelled()->create(['first_date' => '2026-12-07', 'last_date' => '2026-12-11']);
    TimeOff::factory()->create();

    actingAs($cara)->get(route('time-off.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('time-off/Index')
            ->where('year', 2026)
            ->where('today', '2026-12-01')
            ->where('paidDaysLeft', 11)
            ->where('timeOff', [
                ['id' => $newYear->id, 'first_date' => '2026-12-28', 'last_date' => '2027-01-08', 'days' => 10, 'is_paid' => true, 'state' => 'pending', 'decided_by' => null, 'decline_reason' => null, 'can_cancel' => true],
                ['id' => $unpaid->id, 'first_date' => '2026-12-14', 'last_date' => '2026-12-14', 'days' => 1, 'is_paid' => false, 'state' => 'pending', 'decided_by' => null, 'decline_reason' => null, 'can_cancel' => true],
                ['id' => $cancelled->id, 'first_date' => '2026-12-07', 'last_date' => '2026-12-11', 'days' => 5, 'is_paid' => true, 'state' => 'cancelled', 'decided_by' => null, 'decline_reason' => null, 'can_cancel' => false],
                ['id' => $february->id, 'first_date' => '2026-02-02', 'last_date' => '2026-02-06', 'days' => 5, 'is_paid' => true, 'state' => 'approved', 'decided_by' => 'Ben', 'decline_reason' => null, 'can_cancel' => false],
            ]));
});

it('shows negative Paid Days left once HR has lowered them below what is taken', function () {
    $cara = timeOffRequester(['paid_days' => 15]);
    TimeOff::factory()->for($cara)->approved()->create(['first_date' => '2026-02-02', 'last_date' => '2026-02-27']);

    actingAs($cara)->get(route('time-off.index'))
        ->assertInertia(fn (Assert $page) => $page->where('paidDaysLeft', -5));
});

it('refuses Paid Time Off while Paid Days left are negative, but still takes Unpaid', function () {
    $cara = timeOffRequester(['paid_days' => 15]);
    TimeOff::factory()->for($cara)->approved()->create(['first_date' => '2026-02-02', 'last_date' => '2026-02-27']);

    actingAs($cara)->post(route('time-off.store'), timeOffForm(['first_date' => '2026-12-14', 'last_date' => '2026-12-14']))
        ->assertSessionHasErrors(['is_paid' => 'You have no Paid Days left in 2026, since 5 more are already taken than you have. Request this as Unpaid Time Off.']);
    actingAs($cara)->post(route('time-off.store'), timeOffForm(['first_date' => '2026-12-14', 'last_date' => '2026-12-14', 'is_paid' => false]))
        ->assertSessionHasNoErrors();

    expect(storedTimeOff($cara))->toHaveCount(2);
});

it('needs signing in to see or request Time Off', function () {
    get(route('time-off.index'))->assertRedirect(route('login'));
});
