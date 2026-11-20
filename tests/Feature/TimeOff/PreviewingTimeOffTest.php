<?php

use App\Http\Controllers\TimeOff\TimeOffPreviewController;
use App\Http\Requests\TimeOff\TimeOffPreviewRequest;
use App\Models\Employee;
use App\Models\TimeOff;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\travelTo;

covers(TimeOffPreviewController::class, TimeOffPreviewRequest::class, TimeOff::class, Employee::class);

beforeEach(function () {
    travelTo('2026-12-01 09:00');
});

function timeOffPreviewer(): Employee
{
    return Employee::factory()->startedLongAgo()->create(['name' => 'Dev', 'paid_days' => 20]);
}

/** @param  array<string, mixed>  $changes */
function timeOffPreview(array $changes = []): string
{
    return route('time-off.preview', ['first_date' => '2026-12-14', 'last_date' => '2026-12-16', 'is_paid' => 1, ...$changes]);
}

it('counts only weekdays: Fri 18 to Mon 21 Dec is 2 days, using 2 Paid Days', function () {
    actingAs(timeOffPreviewer())->getJson(timeOffPreview(['first_date' => '2026-12-18', 'last_date' => '2026-12-21']))
        ->assertOk()
        ->assertExactJson(['weekdays' => 2, 'years' => [['year' => 2026, 'uses' => 2, 'left_after' => 18]]]);
});

it('splits Paid Time Off across New Year: 28 Dec 2026 to 8 Jan 2027 uses 4 days of 2026 and 6 of 2027', function () {
    actingAs(timeOffPreviewer())->getJson(timeOffPreview(['first_date' => '2026-12-28', 'last_date' => '2027-01-08']))
        ->assertOk()
        ->assertExactJson(['weekdays' => 10, 'years' => [
            ['year' => 2026, 'uses' => 4, 'left_after' => 16],
            ['year' => 2027, 'uses' => 6, 'left_after' => 14],
        ]]);
});

it('counts Pending and Approved Paid Time Off, not Unpaid, Declined or Cancelled, and goes below zero when too few are left', function () {
    $dev = timeOffPreviewer();
    TimeOff::factory()->for($dev)->approved()->create(['first_date' => '2026-02-02', 'last_date' => '2026-02-24']);
    TimeOff::factory()->for($dev)->create(['first_date' => '2026-12-07', 'last_date' => '2026-12-08']);
    TimeOff::factory()->for($dev)->unpaid()->create(['first_date' => '2026-12-09', 'last_date' => '2026-12-11']);
    TimeOff::factory()->for($dev)->declined()->create(['first_date' => '2026-12-21', 'last_date' => '2026-12-23']);
    TimeOff::factory()->for($dev)->cancelled()->create(['first_date' => '2026-12-28', 'last_date' => '2026-12-30']);

    actingAs($dev)->getJson(timeOffPreview())
        ->assertOk()
        ->assertExactJson(['weekdays' => 3, 'years' => [['year' => 2026, 'uses' => 3, 'left_after' => -2]]]);
});

it('uses no Paid Days for Unpaid Time Off', function () {
    actingAs(timeOffPreviewer())->getJson(timeOffPreview(['last_date' => '2027-01-08', 'is_paid' => 0]))
        ->assertOk()
        ->assertExactJson(['weekdays' => 20, 'years' => []]);
});

it('previews only the signed-in Employee\'s own Paid Days, whatever Employee is asked for', function () {
    $dev = timeOffPreviewer();
    $other = timeOffPreviewer();
    TimeOff::factory()->for($other)->approved()->create(['first_date' => '2026-02-02', 'last_date' => '2026-02-27']);

    actingAs($dev)->getJson(timeOffPreview(['employee' => $other->id, 'employee_id' => $other->id]))
        ->assertOk()
        ->assertExactJson(['weekdays' => 3, 'years' => [['year' => 2026, 'uses' => 3, 'left_after' => 17]]]);
});

it('doesn\'t check overlaps, which are refused only on requesting', function () {
    $dev = timeOffPreviewer();
    TimeOff::factory()->for($dev)->create(['first_date' => '2026-12-14', 'last_date' => '2026-12-14']);

    actingAs($dev)->getJson(timeOffPreview())
        ->assertOk()
        ->assertExactJson(['weekdays' => 3, 'years' => [['year' => 2026, 'uses' => 3, 'left_after' => 16]]]);
});

it('refuses invalid or incomplete dates', function (array $input, string $field, string $message) {
    actingAs(timeOffPreviewer())->getJson(timeOffPreview($input))
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors([$field => $message]);
})->with([
    'yesterday' => [['first_date' => '2026-11-30'], 'first_date', 'Time Off can\'t start in the past.'],
    'last date before the first' => [['last_date' => '2026-12-11'], 'last_date', 'The last date field must be a date after or equal to first date.'],
    'no first date' => [['first_date' => ''], 'first_date', 'The first date field is required.'],
    'no last date' => [['last_date' => ''], 'last_date', 'The last date field is required.'],
    'first date not a date' => [['first_date' => '14 Dec 2026'], 'first_date', 'The first date field must match the format Y-m-d.'],
    'last date not a date' => [['last_date' => '2026-12'], 'last_date', 'The last date field must match the format Y-m-d.'],
    'neither Paid nor Unpaid' => [['is_paid' => ''], 'is_paid', 'Choose Paid or Unpaid Time Off.'],
    'not Paid or Unpaid' => [['is_paid' => 'maybe'], 'is_paid', 'Choose Paid or Unpaid Time Off.'],
]);

it('needs signing in to preview Time Off', function () {
    getJson(timeOffPreview())->assertUnauthorized();
});
