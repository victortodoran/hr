<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\WorkEntries\WorkEntryExportController;
use App\Http\Requests\WorkEntries\WorkEntryExportRequest;
use App\Models\Client;
use App\Models\Employee;
use App\Models\WorkEntry;
use Illuminate\Testing\TestResponse;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\SheetIteratorInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(WorkEntryExportController::class, WorkEntryExportRequest::class);

beforeEach(function () {
    travelTo('2026-11-11 09:00');
});

/**
 * The cell values of a downloaded CSV file, header first, as text.
 *
 * @param  TestResponse<Response>  $response
 * @return list<array<int, mixed>>
 */
function csvRows(TestResponse $response): array
{
    return downloadedRows($response, new CsvReader);
}

/**
 * The cell values of a downloaded Excel file's first sheet, header first.
 *
 * @param  TestResponse<Response>  $response
 * @return list<array<int, mixed>>
 */
function xlsxRows(TestResponse $response): array
{
    return downloadedRows($response, new XlsxReader);
}

/**
 * The rows of the downloaded file's first sheet, read back with the reader.
 *
 * @param  TestResponse<Response>  $response
 *
 * @template TSheets of SheetIteratorInterface
 *
 * @param  ReaderInterface<TSheets>  $reader
 * @return list<array<int, mixed>>
 */
function downloadedRows(TestResponse $response, ReaderInterface $reader): array
{
    $path = tempnam(sys_get_temp_dir(), 'export');
    file_put_contents($path, $response->streamedContent());
    $reader->open($path);
    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
        break;
    }
    $reader->close();
    unlink($path);

    return $rows;
}

it('downloads the matching Work Entries as a CSV file, one row each with every column', function () {
    $dan = employeeWith([Permission::ViewWorkEntries->value => Scope::Everyone], ['name' => 'Dan']);
    $cara = Employee::factory()->create(['name' => 'Cara']);
    $relaunch = assignmentOf($cara, 'Relaunch');
    $relaunch->project->client()->associate(Client::factory()->create(['name' => 'Acme']))->save();
    $internal = assignmentOf($cara, 'Internal');
    WorkEntry::factory()->for($relaunch)->approved($dan)->locked($dan)->create(['date' => '2026-11-03', 'minutes' => 450, 'description' => 'Reviewing the API']);
    WorkEntry::factory()->for($relaunch)->declined($dan, 'Wrong Project')->create(['date' => '2026-11-04', 'minutes' => 60, 'description' => 'Standup']);
    WorkEntry::factory()->for($internal)->create(['date' => '2026-11-05', 'minutes' => 180, 'description' => 'Onboarding']);

    $response = actingAs($dan)->get(route('work-entry-exports.show', ['month' => '2026-11', 'employee' => 'all', 'format' => 'csv']));

    $response->assertOk()->assertDownload('work-entries-2026-11-01-to-2026-11-30.csv')->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect(csvRows($response))->toBe([
        ['Date', 'Employee', 'Project', 'Client', 'Hours', 'Description', 'State', 'Approved by', 'Approved on', 'Locked by', 'Locked on', 'Declined by', 'Declined on', 'Decline reason'],
        ['2026-11-03', 'Cara', 'Relaunch', 'Acme', '7.5', 'Reviewing the API', 'Locked', 'Dan', '2026-11-11', 'Dan', '2026-11-11', '', '', ''],
        ['2026-11-04', 'Cara', 'Relaunch', 'Acme', '1', 'Standup', 'Declined', '', '', '', '', 'Dan', '2026-11-11', 'Wrong Project'],
        ['2026-11-05', 'Cara', 'Internal', '', '3', 'Onboarding', 'Pending', '', '', '', '', '', '', ''],
    ]);
});

it('holds only the Work Entries the viewer may see, every page of them, oldest first', function () {
    $ana = employeeWith([Permission::ViewWorkEntries->value => Scope::Reports], ['name' => 'Ana']);
    $cara = Employee::factory()->reportingTo($ana)->create(['name' => 'Cara']);
    $carasRelaunch = assignmentOf($cara, 'Relaunch');
    WorkEntry::factory()->count(30)->for($carasRelaunch)->create(['date' => '2026-11-05']);
    WorkEntry::factory()->for($carasRelaunch)->create(['date' => '2026-11-03']);
    WorkEntry::factory()->for(assignmentOf(Employee::factory()->create(['name' => 'Eve']), 'Billing'))->create(['date' => '2026-11-03']);

    $rows = array_slice(csvRows(actingAs($ana)->get(route('work-entry-exports.show', ['month' => '2026-11', 'employee' => 'all', 'format' => 'csv']))), 1);

    expect(array_column($rows, 1))->toBe(array_fill(0, 31, 'Cara'))
        ->and(array_column($rows, 0))->toBe(['2026-11-03', ...array_fill(0, 30, '2026-11-05')]);
});

it('narrows the file by the Period, Employee, Project and state', function () {
    $dan = employeeWith([Permission::ViewWorkEntries->value => Scope::Everyone]);
    $cara = Employee::factory()->create(['name' => 'Cara']);
    $relaunch = assignmentOf($cara, 'Relaunch', ['start_date' => '2026-10-01']);
    $billing = assignmentOf($cara, 'Billing');
    $bea = assignmentOf(Employee::factory()->create(['name' => 'Bea']), 'Relaunch');
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-10-30', 'description' => 'October']);
    WorkEntry::factory()->for($relaunch)->approved($dan)->create(['date' => '2026-11-03', 'description' => 'Approved Relaunch']);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-04', 'description' => 'Pending Relaunch']);
    WorkEntry::factory()->for($billing)->approved($dan)->create(['date' => '2026-11-05', 'description' => 'Approved Billing']);
    WorkEntry::factory()->for($bea)->approved($dan)->create(['date' => '2026-11-05', 'description' => 'Bea']);

    $rows = csvRows(actingAs($dan)->get(route('work-entry-exports.show', [
        'month' => '2026-11', 'employee' => $cara->id, 'project' => $relaunch->project_id, 'state' => 'approved', 'format' => 'csv',
    ])));

    expect(array_column(array_slice($rows, 1), 5))->toBe(['Approved Relaunch']);
});

it('downloads the same rows as an Excel file, with real dates and numbers', function () {
    $dan = employeeWith([Permission::ViewWorkEntries->value => Scope::Everyone], ['name' => 'Dan']);
    $relaunch = assignmentOf(Employee::factory()->create(['name' => 'Cara']), 'Relaunch');
    WorkEntry::factory()->for($relaunch)->approved($dan)->create(['date' => '2026-11-03', 'minutes' => 450, 'description' => '=1+1']);

    $response = actingAs($dan)->get(route('work-entry-exports.show', ['month' => '2026-11', 'employee' => 'all', 'format' => 'xlsx']));

    $response->assertOk()->assertDownload('work-entries-2026-11-01-to-2026-11-30.xlsx')->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $rows = xlsxRows($response);
    expect($rows[0])->toBe(['Date', 'Employee', 'Project', 'Client', 'Hours', 'Description', 'State', 'Approved by', 'Approved on', 'Locked by', 'Locked on', 'Declined by', 'Declined on', 'Decline reason'])
        ->and($rows[1][0])->toBeInstanceOf(DateTimeImmutable::class)
        ->and($rows[1][0]->format('Y-m-d'))->toBe('2026-11-03')
        ->and(array_slice($rows[1], 1, 7))->toBe(['Cara', 'Relaunch', '', 7.5, '=1+1', 'Approved', 'Dan'])
        ->and($rows[1][8]->format('Y-m-d'))->toBe('2026-11-11');
});

it('keeps text that looks like a formula from running when the CSV file is opened', function () {
    $dan = employeeWith([Permission::ViewWorkEntries->value => Scope::Everyone]);
    $relaunch = assignmentOf(Employee::factory()->create(['name' => 'Cara']), 'Relaunch');
    foreach (['=HYPERLINK("http://evil.test")', '+1', '-1', '@SUM(A1)', '  =1+1', '＝1+1', 'Fixed -1 bug'] as $day => $description) {
        WorkEntry::factory()->for($relaunch)->create(['date' => sprintf('2026-11-%02d', $day + 3), 'description' => $description]);
    }

    $rows = csvRows(actingAs($dan)->get(route('work-entry-exports.show', ['month' => '2026-11', 'employee' => 'all', 'format' => 'csv'])));

    expect(array_column(array_slice($rows, 1), 5))->toBe(["'=HYPERLINK(\"http://evil.test\")", "'+1", "'-1", "'@SUM(A1)", "'  =1+1", "'＝1+1", 'Fixed -1 bug']);
});

it('refuses someone who only sees their own Work Entries, and a missing Employee or unknown format', function () {
    $cara = employeeWith([Permission::ViewWorkEntries->value => Scope::Own]);
    $dan = employeeWith([Permission::ViewWorkEntries->value => Scope::Everyone]);

    actingAs($cara)->get(route('work-entry-exports.show', ['employee' => 'all', 'format' => 'csv']))->assertForbidden();
    actingAs($dan)->get(route('work-entry-exports.show', ['format' => 'csv']))->assertInvalid(['employee']);
    actingAs($dan)->get(route('work-entry-exports.show', ['employee' => 'all', 'format' => 'pdf']))->assertInvalid(['format']);
});

it('leaves out an Employee the viewer may not see, even when asked for by ID', function () {
    $ana = employeeWith([Permission::ViewWorkEntries->value => Scope::Reports]);
    Employee::factory()->reportingTo($ana)->create();
    $eve = Employee::factory()->create();
    WorkEntry::factory()->for(assignmentOf($eve, 'Billing'))->create(['date' => '2026-11-03']);

    $rows = csvRows(actingAs($ana)->get(route('work-entry-exports.show', ['month' => '2026-11', 'employee' => $eve->id, 'format' => 'csv'])));

    expect($rows)->toHaveCount(1);
});
