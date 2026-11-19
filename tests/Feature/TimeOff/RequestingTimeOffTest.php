<?php

use App\Enums\TimeOffState;
use App\Http\Controllers\TimeOff\TimeOffController;
use App\Http\Requests\TimeOff\TimeOffRequest;
use App\Models\Employee;
use App\Models\TimeOff;
use App\Models\TravelRequest;
use App\Models\WorkEntry;
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
    return Employee::factory()->startedLongAgo()->create(['name' => 'Cara', 'paid_days' => 20, 'manager_id' => fn () => Employee::factory()->startedLongAgo()->create()->id, ...$attributes]);
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
    $dev = timeOffRequester(['paid_days' => 0]);

    actingAs($dev)->post(route('time-off.store'), timeOffForm(['first_date' => '2026-12-01', 'last_date' => '2026-12-01', 'is_paid' => false]))
        ->assertSessionHasNoErrors();

    expect(storedTimeOff($dev))->toBe([
        ['first_date' => '2026-12-01', 'last_date' => '2026-12-01', 'is_paid' => false, 'state' => TimeOffState::Pending],
    ]);
});

it('approves the Time Off of an Employee with no Manager at once, with nobody deciding', function () {
    $ana = timeOffRequester(['manager_id' => null]);

    actingAs($ana)->post(route('time-off.store'), timeOffForm())->assertSessionHasNoErrors();

    $timeOff = TimeOff::query()->firstOrFail();
    expect($timeOff->state)->toBe(TimeOffState::Approved);
    expect($timeOff->decided_by)->toBeNull();
    expect($timeOff->decided_at)->toEqual(now());
});

it('refuses Time Off with no weekdays in it', function () {
    $cara = timeOffRequester();

    actingAs($cara)->post(route('time-off.store'), timeOffForm(['first_date' => '2026-12-19', 'last_date' => '2026-12-20']))
        ->assertSessionHasErrors(['first_date' => 'Time Off must include at least one weekday; weekends don\'t count.']);

    expect(storedTimeOff($cara))->toBe([]);
});

it('refuses dates in the past, after the Leaving Date or not a date, with one message each', function (array $attributes, array $input, string $field, string $message) {
    $cara = timeOffRequester($attributes);

    actingAs($cara)->post(route('time-off.store'), timeOffForm($input))
        ->assertSessionHasErrors([$field => $message]);

    expect(session('errors')->get($field))->toBe([$message]);
    expect(storedTimeOff($cara))->toBe([]);
})->with([
    'yesterday' => [[], ['first_date' => '2026-11-30'], 'first_date', 'Time Off can\'t start in the past.'],
    'after the Leaving Date' => [['leaving_date' => '2026-12-15'], [], 'last_date', 'Your Leaving Date is 15 Dec 2026, so Time Off must end by then.'],
    'last date not a date' => [[], ['last_date' => 'soon'], 'last_date', 'The last date field must match the format Y-m-d.'],
]);

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

it('refuses Time Off sharing a day with the Employee\'s own Pending or Approved Travel Request', function (string $state) {
    $cara = timeOffRequester();
    $factory = TravelRequest::factory()->on(assignmentOf($cara));
    ($state === 'pending' ? $factory : $factory->approved())->create(['first_date' => '2026-12-12', 'last_date' => '2026-12-14']);

    actingAs($cara)->post(route('time-off.store'), timeOffForm())
        ->assertSessionHasErrors(['first_date' => 'You have a Travel Request from 12 Dec 2026 to 14 Dec 2026, so Time Off can\'t overlap it.']);

    expect(storedTimeOff($cara))->toBe([]);
})->with(['pending', 'approved']);

it('allows Time Off over Declined or Cancelled Travel Requests, someone else\'s, or right next to one', function () {
    $cara = timeOffRequester();
    $relaunch = assignmentOf($cara);
    TravelRequest::factory()->on($relaunch)->declined()->create(['first_date' => '2026-12-14', 'last_date' => '2026-12-16']);
    TravelRequest::factory()->on($relaunch)->cancelled()->create(['first_date' => '2026-12-15', 'last_date' => '2026-12-15']);
    TravelRequest::factory()->create(['first_date' => '2026-12-14', 'last_date' => '2026-12-16']);
    TravelRequest::factory()->on($relaunch)->create(['first_date' => '2026-12-17', 'last_date' => '2026-12-18']);

    actingAs($cara)->post(route('time-off.store'), timeOffForm())->assertSessionHasNoErrors();

    expect(storedTimeOff($cara))->toHaveCount(1);
});

it('refuses Time Off over a day the Employee has Pending or Approved hours on, naming the earliest', function (string $state) {
    $cara = timeOffRequester();
    $relaunch = assignmentOf($cara);
    $factory = WorkEntry::factory()->for($relaunch);
    ($state === 'approved' ? $factory->approved() : $factory)->create(['date' => '2026-12-02']);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-12-03']);

    actingAs($cara)->post(route('time-off.store'), timeOffForm(['first_date' => '2026-12-01', 'last_date' => '2026-12-04']))
        ->assertSessionHasErrors(['first_date' => 'You have hours recorded on 2 Dec 2026, so Time Off can\'t include that day.']);

    expect(storedTimeOff($cara))->toBe([]);
})->with(['pending', 'approved']);

it('allows Time Off over Declined hours, weekend hours, someone else\'s, or right next to them', function () {
    $cara = timeOffRequester();
    $relaunch = assignmentOf($cara);
    WorkEntry::factory()->for($relaunch)->declined()->create(['date' => '2026-12-14']);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-12-12']);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-12-11']);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-12-17']);
    WorkEntry::factory()->for(assignmentOf(Employee::factory()->create()))->create(['date' => '2026-12-15']);

    actingAs($cara)->post(route('time-off.store'), timeOffForm(['first_date' => '2026-12-12']))->assertSessionHasNoErrors();

    expect(storedTimeOff($cara))->toHaveCount(1);
});

it('refuses Paid Time Off beyond the Paid Days left, counting Pending and Approved Paid Time Off', function () {
    $cara = timeOffRequester();
    TimeOff::factory()->for($cara)->approved()->create(['first_date' => '2026-02-02', 'last_date' => '2026-02-24']);
    TimeOff::factory()->for($cara)->create(['first_date' => '2026-12-07', 'last_date' => '2026-12-08']);

    actingAs($cara)->post(route('time-off.store'), timeOffForm())
        ->assertSessionHasErrors(['is_paid' => 'You have 1 Paid Day left in 2026, but this Paid Time Off takes 3. Request the rest as Unpaid Time Off.']);

    expect(TimeOff::query()->count())->toBe(2);
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
    $cara = Employee::factory()->startedLongAgo()->create(['paid_days' => 10]);
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
    $cara = Employee::factory()->startedLongAgo()->create(['paid_days' => 1]);
    TimeOff::factory()->for($cara)->approved()->create(['first_date' => '2026-12-31', 'last_date' => '2026-12-31']);
    TimeOff::factory()->for($cara)->create(['first_date' => '2027-01-01', 'last_date' => '2027-01-01']);

    actingAs($cara)->post(route('time-off.store'), timeOffForm(['first_date' => '2026-12-30', 'last_date' => '2026-12-30']))
        ->assertSessionHasErrors(['is_paid' => 'You have 0 Paid Days left in 2026, but this Paid Time Off takes 1. Request the rest as Unpaid Time Off.']);
    actingAs($cara)->post(route('time-off.store'), timeOffForm(['first_date' => '2027-01-04', 'last_date' => '2027-01-04']))
        ->assertSessionHasErrors(['is_paid' => 'You have 0 Paid Days left in 2027, but this Paid Time Off takes 1. Request the rest as Unpaid Time Off.']);
});

it('shows the Employee their own Time Off, latest first, and the Paid Days left this year and, in December, next year', function () {
    $cara = timeOffRequester();
    $february = TimeOff::factory()->for($cara)->approved(Employee::factory()->create(['name' => 'Ben']))->create(['first_date' => '2026-02-02', 'last_date' => '2026-02-06']);
    $unpaid = TimeOff::factory()->for($cara)->unpaid()->create(['first_date' => '2026-12-14', 'last_date' => '2026-12-14']);
    $newYear = TimeOff::factory()->for($cara)->create(['first_date' => '2026-12-28', 'last_date' => '2027-01-08']);
    $cancelled = TimeOff::factory()->for($cara)->cancelled()->create(['first_date' => '2026-12-07', 'last_date' => '2026-12-11']);
    TimeOff::factory()->create();

    actingAs($cara)->get(route('time-off.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('time-off/Index')
            ->where('paidDaysLeft', [['year' => 2026, 'days' => 11], ['year' => 2027, 'days' => 14]])
            ->where('timeOff', [
                ['id' => $newYear->id, 'first_date' => '2026-12-28', 'last_date' => '2027-01-08', 'days' => 10, 'is_paid' => true, 'state' => 'pending', 'decided_by' => null, 'decline_reason' => null, 'can_cancel' => true],
                ['id' => $unpaid->id, 'first_date' => '2026-12-14', 'last_date' => '2026-12-14', 'days' => 1, 'is_paid' => false, 'state' => 'pending', 'decided_by' => null, 'decline_reason' => null, 'can_cancel' => true],
                ['id' => $cancelled->id, 'first_date' => '2026-12-07', 'last_date' => '2026-12-11', 'days' => 5, 'is_paid' => true, 'state' => 'cancelled', 'decided_by' => null, 'decline_reason' => null, 'can_cancel' => false],
                ['id' => $february->id, 'first_date' => '2026-02-02', 'last_date' => '2026-02-06', 'days' => 5, 'is_paid' => true, 'state' => 'approved', 'decided_by' => 'Ben', 'decline_reason' => null, 'can_cancel' => false],
            ]));
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

it('opens the form for requesting Time Off on its own page, with today as the earliest first date', function () {
    actingAs(timeOffRequester())->get(route('time-off.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('time-off/Create')
            ->where('today', '2026-12-01'));
});

it('needs signing in to see or request Time Off', function () {
    get(route('time-off.index'))->assertRedirect(route('login'));
    get(route('time-off.create'))->assertRedirect(route('login'));
});

it('accepts Time Off covering exactly a year and refuses one a day longer, as longer leave is several requests', function (string $firstDate, string $lastYearDay, string $dayOver) {
    $cara = timeOffRequester();

    actingAs($cara)->post(route('time-off.store'), timeOffForm(['first_date' => $firstDate, 'last_date' => $dayOver, 'is_paid' => false]))
        ->assertSessionHasErrors(['last_date' => 'The longest Time Off is a year. Request longer leave as several Time Off.']);

    expect(storedTimeOff($cara))->toBe([]);

    actingAs($cara)->post(route('time-off.store'), timeOffForm(['first_date' => $firstDate, 'last_date' => $lastYearDay, 'is_paid' => false]))
        ->assertSessionHasNoErrors();

    expect(storedTimeOff($cara))->toHaveCount(1);
})->with([
    'from 14 Dec' => ['2026-12-14', '2027-12-13', '2027-12-14'],
    'from a leap day' => ['2028-02-29', '2029-02-27', '2029-02-28'],
]);

it('gives only the first date\'s error when it is missing or in the past, not the year limit too', function (string $firstDate, string $message) {
    actingAs(timeOffRequester())->post(route('time-off.store'), timeOffForm(['first_date' => $firstDate, 'last_date' => '2028-06-01', 'is_paid' => false]))
        ->assertOnlyInvalid(['first_date' => $message]);
})->with([
    'missing' => ['', 'The first date field is required.'],
    'in the past' => ['2026-11-30', 'Time Off can\'t start in the past.'],
]);

it('shows the Employee only this year\'s Paid Days left on their Time Off page before October', function () {
    travelTo('2026-09-30 09:00');
    $cara = timeOffRequester();
    TimeOff::factory()->for($cara)->create(['first_date' => '2027-01-04', 'last_date' => '2027-01-04']);

    actingAs($cara)->get(route('time-off.index'))
        ->assertInertia(fn (Assert $page) => $page->where('paidDaysLeft', [['year' => 2026, 'days' => 20]]));
});
