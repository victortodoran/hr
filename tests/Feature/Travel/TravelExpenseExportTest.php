<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\Travel\TravelExpenseExportController;
use App\Http\Requests\Travel\TravelExpenseExportRequest;
use App\Models\CompanySettings;
use App\Models\Employee;
use App\Models\TravelExpense;
use App\Models\TravelRequest;
use App\Pages\Travel\TravelRequestListPage;
use App\Support\Spreadsheet;
use App\Support\TravelExpenseSpreadsheet;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(TravelExpenseExportController::class, TravelExpenseExportRequest::class, TravelRequestListPage::class, TravelExpenseSpreadsheet::class, Spreadsheet::class);

beforeEach(function () {
    travelTo('2026-11-18 09:00');
    CompanySettings::factory()->create(['currency' => 'RON']);
});

/**
 * Fay from Finance, who may view the travel of everyone (or the given
 * grants instead).
 *
 * @param  array<string, Scope|null>|null  $grants
 */
function travelExpenseExporter(?array $grants = null): Employee
{
    return employeeWith($grants ?? [Permission::ViewTravel->value => Scope::Everyone], ['name' => 'Fay']);
}

/**
 * An Approved Travel Request of the Employee's on their Assignment to the
 * Project, from the first to the last date.
 */
function exportedTripOf(Employee $employee, string $destination, string $first = '2026-11-03', string $last = '2026-11-05', string $project = 'Relaunch'): TravelRequest
{
    return TravelRequest::factory()->on(assignmentOf($employee, $project))->approved()
        ->create(['destination' => $destination, 'first_date' => $first, 'last_date' => $last]);
}

it('downloads the Approved Travel Expenses paid in the month as a CSV file, by date paid, with every column', function () {
    $fay = travelExpenseExporter();
    $berlin = exportedTripOf(Employee::factory()->create(['name' => 'Cara']), 'Berlin', '2026-10-28', '2026-11-02');
    TravelExpense::factory()->for($berlin)->approved()->create(['paid_on' => '2026-11-02', 'category' => 'accommodation', 'description' => 'Hotel', 'amount' => 199050]);
    TravelExpense::factory()->for($berlin)->approved()->create(['paid_on' => '2026-10-28', 'category' => 'transport', 'description' => 'Flight', 'amount' => 80000]);
    TravelExpense::factory()->for($berlin)->approved()->create(['paid_on' => '2026-11-01', 'category' => 'meals', 'description' => '=1+1', 'amount' => 4500]);
    TravelExpense::factory()->for($berlin)->create(['paid_on' => '2026-11-01', 'description' => 'Pending taxi']);
    TravelExpense::factory()->for($berlin)->declined()->create(['paid_on' => '2026-11-01', 'description' => 'Declined taxi']);

    $response = actingAs($fay)->get(route('travel-expense-exports.show', ['month' => '2026-11', 'format' => 'csv']));

    $response->assertOk()->assertDownload('travel-expenses-2026-11-01-to-2026-11-30.csv')->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect(csvRows($response))->toBe([
        ['Employee', 'Destination', 'First date', 'Last date', 'Paid on', 'Category', 'Description', 'Amount (RON)'],
        ['Cara', 'Berlin', '2026-10-28', '2026-11-02', '2026-11-01', 'Meals', "'=1+1", '45'],
        ['Cara', 'Berlin', '2026-10-28', '2026-11-02', '2026-11-02', 'Accommodation', 'Hotel', '1990.5'],
    ]);
});

it('downloads the same rows as an Excel file, with real dates and numbers', function () {
    $fay = travelExpenseExporter();
    $berlin = exportedTripOf(Employee::factory()->create(['name' => 'Cara']), 'Berlin');
    TravelExpense::factory()->for($berlin)->approved()->create(['paid_on' => '2026-11-04', 'category' => 'other', 'description' => 'Visa', 'amount' => 12345]);

    $response = actingAs($fay)->get(route('travel-expense-exports.show', ['month' => '2026-11', 'format' => 'xlsx']));

    $response->assertOk()->assertDownload('travel-expenses-2026-11-01-to-2026-11-30.xlsx');
    $rows = xlsxRows($response);
    expect(array_map(fn (mixed $cell): mixed => $cell instanceof DateTimeInterface ? $cell->format('Y-m-d') : $cell, $rows[1]))
        ->toBe(['Cara', 'Berlin', '2026-11-03', '2026-11-05', '2026-11-04', 'Other', 'Visa', 123.45]);
});

it('holds only the Travel Expenses of the Employees the viewer may see travel of', function () {
    $ana = travelExpenseExporter([Permission::ViewTravel->value => Scope::Reports]);
    TravelExpense::factory()->for(exportedTripOf(Employee::factory()->reportingTo($ana)->create(), 'Berlin'))->approved()->create(['paid_on' => '2026-11-04', 'description' => 'Report']);
    $eve = Employee::factory()->create();
    TravelExpense::factory()->for(exportedTripOf($eve, 'Rome'))->approved()->create(['paid_on' => '2026-11-04', 'description' => 'Not a report']);

    $rows = csvRows(actingAs($ana)->get(route('travel-expense-exports.show', ['month' => '2026-11', 'format' => 'csv'])));
    $askedForEve = csvRows(actingAs($ana)->get(route('travel-expense-exports.show', ['month' => '2026-11', 'employee' => $eve->id, 'format' => 'csv'])));

    expect(array_column(array_slice($rows, 1), 6))->toBe(['Report'])
        ->and($askedForEve)->toHaveCount(1);
});

it('narrows the file by the Employee and Project, and ignores the Travel Requests\' state filter', function () {
    $fay = travelExpenseExporter();
    $cara = Employee::factory()->create(['name' => 'Cara']);
    TravelExpense::factory()->for(exportedTripOf($cara, 'Berlin'))->approved()->create(['paid_on' => '2026-11-04', 'description' => 'Relaunch']);
    TravelExpense::factory()->for(exportedTripOf($cara, 'Rome', project: 'Atlas'))->approved()->create(['paid_on' => '2026-11-04', 'description' => 'Atlas']);
    TravelExpense::factory()->for(exportedTripOf(Employee::factory()->create(), 'Paris'))->approved()->create(['paid_on' => '2026-11-04', 'description' => 'Someone else']);
    $relaunch = $cara->assignments()->whereRelation('project', 'name', 'Relaunch')->sole();

    $rows = csvRows(actingAs($fay)->get(route('travel-expense-exports.show', [
        'month' => '2026-11', 'employee' => $cara->id, 'project' => $relaunch->project_id, 'state' => 'pending', 'format' => 'csv',
    ])));

    expect(array_column(array_slice($rows, 1), 6))->toBe(['Relaunch']);
});

it('refuses someone who only sees their own travel, and an unknown format', function () {
    actingAs(Employee::factory()->create())->get(route('travel-expense-exports.show', ['format' => 'csv']))->assertForbidden();
    actingAs(travelExpenseExporter())->get(route('travel-expense-exports.show', ['format' => 'pdf']))->assertInvalid(['format']);
});
