<?php

use App\Enums\WorkEntriesTab;
use App\Http\Controllers\WorkEntries\WorkEntryController;
use App\Http\Requests\WorkEntries\WorkEntryPeriodRequest;
use App\Models\WorkEntry;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(WorkEntryController::class, WorkEntryPeriodRequest::class, WorkEntriesTab::class);

beforeEach(function () {
    travelTo('2026-11-11 09:00');
});

it('shows a month as ISO weeks, Monday to Sunday, each day as the List has it, leaving out days of other months, with the Assignments to add to', function () {
    $cara = recorder();
    $relaunch = assignmentOf($cara);
    $acme = assignmentOf($cara, 'Acme');
    $review = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-03', 'minutes' => 180, 'description' => 'Review']);
    $specs = WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2026-11-03', 'minutes' => 90, 'description' => 'Specs']);
    $release = WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2026-11-03', 'minutes' => 30, 'description' => 'Release']);
    $meeting = WorkEntry::factory()->for($acme)->create(['date' => '2026-11-03', 'minutes' => 150, 'description' => 'Team meeting']);
    $late = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-30', 'minutes' => 60, 'description' => 'Late fix']);

    $response = actingAs($cara)->get(route('work-entries.index', ['tab' => 'calendar', 'month' => '2026-11']));

    $entry = fn (WorkEntry $entry, string $project, float|int $hours, string $state): array => [
        'id' => $entry->id, 'assignment_id' => $entry->assignment_id, 'project' => $project, 'hours' => $hours,
        'description' => $entry->description, 'state' => $state, 'can_edit' => $state === 'pending', 'can_delete' => $state === 'pending', 'declined' => null,
    ];

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('tab', 'calendar')
        ->where('period', ['mode' => 'month', 'from' => '2026-11-01', 'to' => '2026-11-30', 'query' => ['month' => '2026-11']])
        ->where('total', 8.5)
        ->has('calendar', 6)
        ->where('calendar.0.number', 44)
        ->where('calendar.0.hours', 0)
        ->where('calendar.0.days', [null, null, null, null, null, null, ['date' => '2026-11-01', 'entries' => [], 'hours' => 0, 'time_off' => null, 'nothing_logged' => false, 'copy_from' => null]])
        ->where('calendar.1.number', 45)
        ->where('calendar.1.hours', 7.5)
        ->where('calendar.1.days.1.date', '2026-11-03')
        ->where('calendar.1.days.1.hours', 7.5)
        ->where('calendar.1.days.1.entries', [
            $entry($review, 'Relaunch', 3, 'pending'),
            $entry($specs, 'Relaunch', 1.5, 'approved'),
            $entry($release, 'Relaunch', 0.5, 'approved'),
            $entry($meeting, 'Acme', 2.5, 'pending'),
        ])
        ->where('calendar.1.days.0.nothing_logged', true)
        ->where('calendar.5.number', 49)
        ->where('calendar.5.days.0.entries', [$entry($late, 'Relaunch', 1, 'pending')])
        ->where('calendar.5.days.1', null)
        ->has('assignments', 2)
        ->missing('days')
        ->missing('weeks'));
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

it('goes back to the Calendar on the month of the day after adding or deleting a Work Entry from it', function () {
    $cara = recorder();
    $relaunch = assignmentOf($cara);
    $entry = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10']);

    actingAs($cara)->post(route('employees.work-entries.store', ['employee' => $cara, 'tab' => 'calendar', 'month' => '2026-11']), workEntryForm($relaunch))
        ->assertRedirect(route('work-entries.index', ['employee' => $cara->id, 'tab' => 'calendar', 'month' => '2026-11']));
    actingAs($cara)->delete(route('work-entries.destroy', ['workEntry' => $entry, 'tab' => 'calendar', 'month' => '2026-11']))
        ->assertRedirect(route('work-entries.index', ['employee' => $cara->id, 'tab' => 'calendar', 'month' => '2026-11']));
});
