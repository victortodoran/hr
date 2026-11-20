<?php

use App\Enums\Permission;
use App\Http\Controllers\HomeController;
use App\Models\Employee;
use App\Models\TimeOff;
use App\Models\TravelExpense;
use App\Models\TravelRequest;
use App\Models\WorkEntry;
use App\Pages\HomePage;
use App\Support\PaidDaysLeft;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\travelTo;

covers(HomeController::class, HomePage::class, PaidDaysLeft::class, Employee::class);

beforeEach(function () {
    travelTo('2026-11-11 09:00');
});

it('sends guests from the home page straight to sign in', function () {
    get('/')->assertRedirect(route('login'));
    get(route('landing'))->assertRedirect(route('login'));
});

it('lands every Employee on Home after signing in', function () {
    $cara = recorder();

    post(route('login.store'), ['email' => $cara->email, 'password' => 'password'])
        ->assertRedirect(route('landing', absolute: false));

    actingAs($cara)->get('/')->assertRedirect(route('landing'));
    actingAs($cara)->get(route('landing'))
        ->assertInertia(fn (Assert $page) => $page->component('Home'));
});

it('shows a regular Employee their hours this month, Paid Days left and next Time Off, with nothing waiting for them', function () {
    $cara = recorder(['manager_id' => Employee::factory()->create()->id, 'paid_days' => 20]);
    $relaunch = assignmentOf($cara, 'Relaunch', ['start_date' => '2026-10-01']);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-02', 'minutes' => 90]);
    WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2026-11-10', 'minutes' => 360]);
    WorkEntry::factory()->for($relaunch)->declined()->create(['date' => '2026-11-05', 'minutes' => 60]);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-10-31', 'minutes' => 480]);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-12', 'minutes' => 480]);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-12-01', 'minutes' => 480]);
    $next = TimeOff::factory()->for($cara)->approved()->create(['first_date' => '2026-12-21', 'last_date' => '2026-12-23']);

    actingAs($cara)->get(route('landing'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('hasNoManager', false)
            ->where('hoursThisMonth', ['month' => '2026-11', 'hours' => 7.5, 'pendingHours' => 1.5])
            ->where('paidDaysLeft', [['year' => 2026, 'days' => 17], ['year' => 2027, 'days' => 20]])
            ->where('nextTimeOff.id', $next->id)
            ->where('nextTimeOff.state', 'approved')
            ->where('needsAttention.items.0.date', '2026-11-05')
            ->where('waitingForYou', null),
        );
});

it('shows a Manager what waits for them, matching the Approvals badge', function () {
    $ada = recorder(['name' => 'Ada']);
    $ben = recorder(['name' => 'Ben', 'manager_id' => $ada->id]);
    $relaunch = assignmentOf($ben);
    WorkEntry::factory()->for($relaunch)->count(2)->create(['date' => '2026-11-10']);
    WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2026-11-10']);
    TimeOff::factory()->for($ben)->create();
    TravelRequest::factory()->on($relaunch)->create();
    TravelRequest::factory()->on($relaunch)->declined()->create();
    $trip = TravelRequest::factory()->on($relaunch)->approved()->create();
    TravelExpense::factory()->for($trip)->create();
    TravelExpense::factory()->for($trip)->declined()->create();

    actingAs($ada)->get(route('landing'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('waitingForYou', ['workEntries' => 2, 'timeOff' => 1, 'travelRequests' => 1, 'travelExpenses' => 1])
            ->where('auth.approvalsWaiting', 5)
            ->where('hoursThisMonth', ['month' => '2026-11', 'hours' => 0, 'pendingHours' => 0])
            ->where('nextTimeOff', null),
        );
});

it('shows an Employee their hours for each month this year up to this one, leaving out Declined ones and last year\'s, with how many are Pending', function () {
    $cara = recorder(['manager_id' => Employee::factory()->create()->id, 'start_date' => '2024-03-04']);
    $relaunch = assignmentOf($cara, 'Relaunch', ['start_date' => '2025-12-01']);
    WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2025-12-31', 'minutes' => 600]);
    WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2026-01-05', 'minutes' => 480]);
    WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2026-01-30', 'minutes' => 240]);
    WorkEntry::factory()->for($relaunch)->declined()->create(['date' => '2026-03-02', 'minutes' => 300]);
    WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2026-11-02', 'minutes' => 360]);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10', 'minutes' => 90]);

    $empty = fn (string $month): array => ['month' => $month, 'hours' => 0, 'pendingHours' => 0];

    actingAs($cara)->get(route('landing'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('hoursThisYear', [
                'year' => 2026,
                'hours' => 19.5,
                'pendingHours' => 1.5,
                'months' => [
                    ['month' => '2026-01', 'hours' => 12, 'pendingHours' => 0],
                    $empty('2026-02'),
                    $empty('2026-03'),
                    $empty('2026-04'),
                    $empty('2026-05'),
                    $empty('2026-06'),
                    $empty('2026-07'),
                    $empty('2026-08'),
                    $empty('2026-09'),
                    $empty('2026-10'),
                    ['month' => '2026-11', 'hours' => 7.5, 'pendingHours' => 1.5],
                ],
            ]),
        );
});

it('starts an Employee\'s months this year at the month of their Start Date when they started this year', function () {
    $cara = recorder(['manager_id' => Employee::factory()->create()->id, 'start_date' => '2026-09-14']);
    WorkEntry::factory()->for(assignmentOf($cara, 'Relaunch', ['start_date' => '2026-09-14']))->create(['date' => '2026-09-15', 'minutes' => 120]);

    actingAs($cara)->get(route('landing'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('hoursThisYear.months', [
                ['month' => '2026-09', 'hours' => 2, 'pendingHours' => 2],
                ['month' => '2026-10', 'hours' => 0, 'pendingHours' => 0],
                ['month' => '2026-11', 'hours' => 0, 'pendingHours' => 0],
            ]),
        );
});

it('leaves hours this month and this year out for an Employee with no Manager, saying they record no Work Entries', function () {
    $ana = recorder(['manager_id' => null]);
    TimeOff::factory()->for($ana)->approved()->create(['first_date' => '2026-11-11', 'last_date' => '2026-11-11']);

    actingAs($ana)->get(route('landing'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('hasNoManager', true)
            ->where('hoursThisMonth', null)
            ->where('hoursThisYear', null)
            ->where('paidDaysLeft', [['year' => 2026, 'days' => -1], ['year' => 2027, 'days' => 0]])
            ->where('nextTimeOff.first_date', '2026-11-11')
            ->where('waitingForYou', null),
        );
});

it('leaves hours this month and this year, and Declined Work Entries, out for an Employee who can\'t see their Work Entries', function () {
    $cara = employeeWith([Permission::ViewDirectory->value => null], ['manager_id' => Employee::factory()->create()->id]);
    WorkEntry::factory()->for(assignmentOf($cara))->declined()->create(['date' => '2026-11-10']);

    actingAs($cara)->get(route('landing'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('hasNoManager', false)
            ->where('hoursThisMonth', null)
            ->where('hoursThisYear', null)
            ->where('paidDaysLeft', [['year' => 2026, 'days' => 0], ['year' => 2027, 'days' => 0]])
            ->where('needsAttention', null),
        );
});

it('says nothing about Work Entries to an Employee with no Manager who can\'t see them', function () {
    $ana = employeeWith([Permission::ViewDirectory->value => null], ['manager_id' => null]);

    actingAs($ana)->get(route('landing'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('hasNoManager', false)
            ->where('hoursThisMonth', null),
        );
});

it('shows as next Time Off the soonest Pending or Approved Time Off that hasn\'t ended', function () {
    $cara = recorder();
    TimeOff::factory()->for($cara)->approved()->create(['first_date' => '2026-11-02', 'last_date' => '2026-11-10']);
    TimeOff::factory()->for($cara)->declined()->create(['first_date' => '2026-11-12', 'last_date' => '2026-11-12']);
    TimeOff::factory()->for($cara)->cancelled()->create(['first_date' => '2026-11-13', 'last_date' => '2026-11-13']);
    TimeOff::factory()->for($cara)->approved()->create(['first_date' => '2026-12-01', 'last_date' => '2026-12-01']);
    $next = TimeOff::factory()->for($cara)->create(['first_date' => '2026-11-11', 'last_date' => '2026-11-11']);

    actingAs($cara)->get(route('landing'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('nextTimeOff.id', $next->id)
            ->where('nextTimeOff.state', 'pending'),
        );
});

it('lists what of the Employee\'s was declined, with the reason: Work Entries, Travel Requests and Time Off still to come, and Travel Expenses', function () {
    $cara = recorder();
    $relaunch = assignmentOf($cara, 'Relaunch');
    $entry = WorkEntry::factory()->for($relaunch)->declined(reason: 'Split by task')->create(['date' => '2026-11-10', 'minutes' => 90]);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-09']);
    $lisbon = TravelRequest::factory()->on($relaunch)->declined()->create(['destination' => 'Lisbon', 'first_date' => '2026-11-12', 'last_date' => '2026-11-13', 'decline_reason' => 'Launch prep']);
    TravelRequest::factory()->on($relaunch)->declined()->create(['first_date' => '2026-11-11', 'last_date' => '2026-11-11']);
    $berlin = TravelRequest::factory()->on($relaunch)->approved()->create(['destination' => 'Berlin', 'first_date' => '2026-11-03', 'last_date' => '2026-11-05']);
    $hotel = TravelExpense::factory()->for($berlin)->declined()->create(['description' => 'Berlin hotel', 'decline_reason' => 'Receipt unreadable']);
    TravelExpense::factory()->for($berlin)->create();
    $timeOff = TimeOff::factory()->for($cara)->declined()->create(['first_date' => '2026-11-12', 'last_date' => '2026-11-12', 'decline_reason' => 'Launch week']);
    TimeOff::factory()->for($cara)->declined()->create(['first_date' => '2026-11-11', 'last_date' => '2026-11-11']);
    TimeOff::factory()->for($cara)->create(['first_date' => '2026-11-20', 'last_date' => '2026-11-20']);
    TravelExpense::factory()->for(TravelRequest::factory()->approved())->declined()->create();

    actingAs($cara)->get(route('landing'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('needsAttention', [
                'items' => [
                    ['kind' => 'work-entry', 'id' => $entry->id, 'date' => '2026-11-10', 'project' => 'Relaunch', 'hours' => 1.5, 'decline_reason' => 'Split by task'],
                    ['kind' => 'travel-request', 'id' => $lisbon->id, 'destination' => 'Lisbon', 'first_date' => '2026-11-12', 'last_date' => '2026-11-13', 'decline_reason' => 'Launch prep'],
                    ['kind' => 'travel-expense', 'id' => $hotel->id, 'travel_request_id' => $berlin->id, 'destination' => 'Berlin', 'description' => 'Berlin hotel', 'decline_reason' => 'Receipt unreadable'],
                    ['kind' => 'time-off', 'id' => $timeOff->id, 'first_date' => '2026-11-12', 'last_date' => '2026-11-12', 'decline_reason' => 'Launch week'],
                ],
                'more' => 0,
            ]),
        );
});

it('drops a Declined Work Entry once changed and a Declined Travel Expense once deleted', function () {
    Storage::fake(TravelExpense::RECEIPT_DISK);
    $cara = recorder();
    $relaunch = assignmentOf($cara);
    $entry = WorkEntry::factory()->for($relaunch)->declined()->create(['date' => '2026-11-10']);
    $trip = TravelRequest::factory()->on($relaunch)->approved()->create(['first_date' => '2026-11-03', 'last_date' => '2026-11-05']);
    $expense = TravelExpense::factory()->for($trip)->declined()->create();

    actingAs($cara)->get(route('landing'))->assertInertia(fn (Assert $page) => $page->has('needsAttention.items', 2));

    actingAs($cara)->patch(route('work-entries.update', $entry), workEntryForm($relaunch, ['hours' => '2']))->assertSessionHasNoErrors();
    actingAs($cara)->delete(route('travel-expenses.destroy', $expense))->assertSessionHasNoErrors();

    actingAs($cara)->get(route('landing'))->assertInertia(fn (Assert $page) => $page->where('needsAttention', null));
});

it('lists at most ten of each kind that needs attention, counting the rest', function () {
    $cara = recorder();
    $relaunch = assignmentOf($cara);
    $entries = WorkEntry::factory()->for($relaunch)->declined()->count(12)->sequence(fn ($sequence) => ['date' => CarbonImmutable::parse('2026-11-02')->addDays($sequence->index)])->create();
    TimeOff::factory()->for($cara)->declined()->create(['first_date' => '2026-11-20', 'last_date' => '2026-11-20']);

    actingAs($cara)->get(route('landing'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('needsAttention.items', 11)
            ->where('needsAttention.items.0.id', $entries->first()?->id)
            ->where('needsAttention.items.9.id', $entries->get(9)?->id)
            ->where('needsAttention.items.10.kind', 'time-off')
            ->where('needsAttention.more', 2),
        );
});

it('shows next year\'s Paid Days left beside this year\'s from 1 October, counting Pending and Approved Paid Time Off', function (string $today, array $shown) {
    travelTo($today);
    $cara = recorder(['manager_id' => Employee::factory()->create()->id, 'paid_days' => 20]);
    TimeOff::factory()->for($cara)->approved()->create(['first_date' => '2026-12-28', 'last_date' => '2027-01-08']);
    TimeOff::factory()->for($cara)->create(['first_date' => '2027-02-01', 'last_date' => '2027-02-02']);
    TimeOff::factory()->for($cara)->declined()->create(['first_date' => '2027-03-01', 'last_date' => '2027-03-05']);
    TimeOff::factory()->for($cara)->unpaid()->create(['first_date' => '2027-04-05', 'last_date' => '2027-04-09']);

    actingAs($cara)->get(route('landing'))
        ->assertInertia(fn (Assert $page) => $page->where('paidDaysLeft', $shown));
})->with([
    'on 30 September, only this year' => ['2026-09-30 23:59', [['year' => 2026, 'days' => 16]]],
    'on 1 October, next year too' => ['2026-10-01 00:00', [['year' => 2026, 'days' => 16], ['year' => 2027, 'days' => 12]]],
    'on 31 December, next year too' => ['2026-12-31 23:59', [['year' => 2026, 'days' => 16], ['year' => 2027, 'days' => 12]]],
    'on 1 January, only the new year' => ['2027-01-01 00:00', [['year' => 2027, 'days' => 12]]],
]);
