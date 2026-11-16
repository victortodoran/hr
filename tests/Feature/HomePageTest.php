<?php

use App\Enums\Permission;
use App\Http\Controllers\HomeController;
use App\Models\Employee;
use App\Models\TimeOff;
use App\Models\TravelRequest;
use App\Models\WorkEntry;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\travelTo;

covers(HomeController::class, Employee::class);

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
            ->where('hoursThisMonth', ['month' => '2026-11', 'hours' => 7.5, 'pendingHours' => 1.5])
            ->where('paidDaysLeft', ['year' => 2026, 'days' => 17])
            ->where('nextTimeOff.id', $next->id)
            ->where('nextTimeOff.state', 'approved')
            ->where('declinedAhead', null)
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

    actingAs($ada)->get(route('landing'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('waitingForYou', ['workEntries' => 2, 'timeOff' => 1, 'travelRequests' => 1])
            ->where('auth.approvalsWaiting', 4)
            ->where('hoursThisMonth', ['month' => '2026-11', 'hours' => 0, 'pendingHours' => 0])
            ->where('nextTimeOff', null),
        );
});

it('shows an Employee their hours for each month this year up to this one, leaving out Declined ones and last year\'s, with how many are Pending', function () {
    $cara = recorder(['manager_id' => Employee::factory()->create()->id, 'start_date' => '2024-03-04']);
    $relaunch = assignmentOf($cara, 'Relaunch', ['start_date' => '2025-12-01']);
    WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2025-12-31', 'minutes' => 600]);
    WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2026-01-05', 'minutes' => 480]);
    WorkEntry::factory()->for($relaunch)->approved()->locked()->create(['date' => '2026-01-30', 'minutes' => 240]);
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

it('leaves hours this month and this year out for an Employee with no Manager, who records no Work Entries', function () {
    $ana = recorder(['manager_id' => null]);
    TimeOff::factory()->for($ana)->approved()->create(['first_date' => '2026-11-11', 'last_date' => '2026-11-11']);

    actingAs($ana)->get(route('landing'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('hoursThisMonth', null)
            ->where('hoursThisYear', null)
            ->where('paidDaysLeft', ['year' => 2026, 'days' => -1])
            ->where('nextTimeOff.first_date', '2026-11-11')
            ->where('waitingForYou', null),
        );
});

it('leaves hours this month and this year out for an Employee who can\'t see their Work Entries', function () {
    $cara = employeeWith([Permission::ViewDirectory->value => null]);

    actingAs($cara)->get(route('landing'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('hoursThisMonth', null)
            ->where('hoursThisYear', null)
            ->where('paidDaysLeft', ['year' => 2026, 'days' => 0]),
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

it('shows Declined Time Off still to come, with the reason, and only that', function () {
    $cara = recorder();
    $ahead = TimeOff::factory()->for($cara)->declined()->create(['first_date' => '2026-11-12', 'last_date' => '2026-11-12', 'decline_reason' => 'Launch week']);
    TimeOff::factory()->for($cara)->declined()->create(['first_date' => '2026-11-11', 'last_date' => '2026-11-11']);
    TimeOff::factory()->for($cara)->create(['first_date' => '2026-11-20', 'last_date' => '2026-11-20']);

    actingAs($cara)->get(route('landing'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('declinedAhead', 1)
            ->where('declinedAhead.0.id', $ahead->id)
            ->where('declinedAhead.0.decline_reason', 'Launch week'),
        );
});
