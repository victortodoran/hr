<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Enums\WorkEntriesTab;
use App\Http\Controllers\WorkEntryController;
use App\Http\Requests\WorkEntryPeriodRequest;
use App\Models\Employee;
use App\Models\TimeOff;
use App\Models\WorkEntry;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(WorkEntryController::class, WorkEntryPeriodRequest::class, WorkEntriesTab::class);

beforeEach(function () {
    travelTo('2026-11-11 09:00');
});

it('shows a month as ISO weeks, Monday to Sunday, each day with its hours and its Work Entries, leaving out days of other months', function () {
    $cara = recorder();
    $relaunch = assignmentOf($cara);
    $acme = assignmentOf($cara, 'Acme');
    $review = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-03', 'minutes' => 180, 'description' => 'Review']);
    $specs = WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2026-11-03', 'minutes' => 90, 'description' => 'Specs']);
    $release = WorkEntry::factory()->for($relaunch)->approved()->locked()->create(['date' => '2026-11-03', 'minutes' => 30, 'description' => 'Release']);
    $meeting = WorkEntry::factory()->for($acme)->create(['date' => '2026-11-03', 'minutes' => 150, 'description' => 'Team meeting']);
    $late = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-30', 'minutes' => 60, 'description' => 'Late fix']);

    $response = actingAs($cara)->get(route('work-entries.index', ['tab' => 'calendar', 'month' => '2026-11']));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('tab', 'calendar')
        ->where('period', ['mode' => 'month', 'from' => '2026-11-01', 'to' => '2026-11-30', 'query' => ['month' => '2026-11']])
        ->where('total', 8.5)
        ->has('calendar', 6)
        ->where('calendar.0.number', 44)
        ->where('calendar.0.hours', 0)
        ->where('calendar.0.days', [null, null, null, null, null, null, ['date' => '2026-11-01', 'hours' => 0, 'entries' => [], 'time_off' => null, 'nothing_logged' => false]])
        ->where('calendar.1.number', 45)
        ->where('calendar.1.hours', 7.5)
        ->where('calendar.1.days.1', ['date' => '2026-11-03', 'hours' => 7.5, 'entries' => [
            ['id' => $review->id, 'description' => 'Review', 'hours' => 3, 'state' => 'pending'],
            ['id' => $specs->id, 'description' => 'Specs', 'hours' => 1.5, 'state' => 'approved'],
            ['id' => $release->id, 'description' => 'Release', 'hours' => 0.5, 'state' => 'locked'],
            ['id' => $meeting->id, 'description' => 'Team meeting', 'hours' => 2.5, 'state' => 'pending'],
        ], 'time_off' => null, 'nothing_logged' => false])
        ->where('calendar.1.days.0.nothing_logged', true)
        ->where('calendar.5.number', 49)
        ->where('calendar.5.days', [['date' => '2026-11-30', 'hours' => 1, 'entries' => [['id' => $late->id, 'description' => 'Late fix', 'hours' => 1, 'state' => 'pending']], 'time_off' => null, 'nothing_logged' => false], null, null, null, null, null, null])
        ->missing('days')
        ->missing('weeks')
        ->missing('assignments'));
});

it('totals the month on each Project, most hours first', function () {
    $cara = recorder();
    $relaunch = assignmentOf($cara);
    $acme = assignmentOf($cara, 'Acme');
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-03', 'minutes' => 60]);
    WorkEntry::factory()->for($acme)->create(['date' => '2026-11-04', 'minutes' => 150]);

    $response = actingAs($cara)->get(route('work-entries.index', ['tab' => 'calendar', 'month' => '2026-11']));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('assignmentTotals', [
            ['assignment_id' => $acme->id, 'project' => 'Acme', 'hours' => 2.5],
            ['assignment_id' => $relaunch->id, 'project' => 'Relaunch', 'hours' => 1],
        ]));
});

it('marks Time Off on the calendar, in future months too', function () {
    $cara = recorder();
    TimeOff::factory()->for($cara)->unpaid()->create(['first_date' => '2026-12-21', 'last_date' => '2026-12-21']);

    $response = actingAs($cara)->get(route('work-entries.index', ['tab' => 'calendar', 'month' => '2026-12']));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('calendar.3.days.0.date', '2026-12-21')
        ->where('calendar.3.days.0.time_off', 'unpaid'));
});

it('opens the calendar on the month holding the first day of the period asked for', function (array $period) {
    $cara = recorder();

    $response = actingAs($cara)->get(route('work-entries.index', ['tab' => 'calendar', ...$period]));

    $response->assertInertia(fn (Assert $page) => $page->where('period.query', ['month' => '2026-10']));
})->with([
    'a week' => [['week' => '2026-10-28']],
    'a custom period' => [['from' => '2026-10-15', 'to' => '2026-12-01']],
]);

it('opens the calendar on the current month when no period is asked for, even when this week began last month', function () {
    travelTo('2026-12-03 09:00');

    actingAs(recorder())->get(route('work-entries.index', ['tab' => 'calendar']))
        ->assertInertia(fn (Assert $page) => $page->where('period.query', ['month' => '2026-12']));
});

it('refuses a tab that is not a single value', function () {
    actingAs(recorder())->get(route('work-entries.index', ['tab' => ['calendar']]))
        ->assertInvalid(['tab' => 'The tab field must be a string.']);
});

it('opens the list for a tab it doesn\'t know, or none, loading only its own days', function (string $tab) {
    actingAs(recorder())->get(route('work-entries.index', ['tab' => $tab]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'list')
            ->has('days', 7)
            ->missing('calendar'));
})->with(['', 'agenda']);

it('shows the calendar of an Employee the viewer\'s Scope covers, as not their own', function () {
    $ben = employeeWith([Permission::ViewWorkEntries->value => Scope::Reports]);
    $cara = Employee::factory()->create(['manager_id' => $ben->id]);
    WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-03', 'minutes' => 60]);

    actingAs($ben)->get(route('work-entries.index', ['employee' => $cara->id, 'tab' => 'calendar']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('isOwn', false)
            ->where('calendar.1.days.1.hours', 1));
});
