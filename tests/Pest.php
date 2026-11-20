<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\SheetIteratorInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\PendingAwaitablePage;
use Pest\Browser\Api\Webpage;
use Symfony\Component\HttpFoundation\Response;
use Tests\Browser\BrowserTestCase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature test closures are bound to the application's TestCase, so they
| can call routes and use Laravel's testing helpers, and each one starts
| on a fresh database, including tests in folders added later.
|
*/

/*
 * Tests log to storage/logs/testing.log, which holds the real exception
 * behind a failing request. Each run starts it empty, so it only ever holds
 * the latest run and never grows unbounded: `composer test` empties it
 * before its parallel workers start, and a run outside workers empties it
 * here.
 */
if (getenv('TEST_TOKEN') === false) {
    file_put_contents(__DIR__.'/../storage/logs/testing.log', '');
}

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(BrowserTestCase::class)->use(RefreshDatabase::class)->in('Browser');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Create an Employee holding one Role with the given Permissions. Map each
 * Permission value to its Scope, or to null for company-wide Permissions.
 *
 * @param  array<string, Scope|null>  $grants
 * @param  array<string, mixed>  $attributes
 */
function employeeWith(array $grants, array $attributes = []): Employee
{
    $employee = Employee::factory()->create($attributes);

    giveRole($employee, $grants);

    return $employee;
}

/**
 * An Employee who may add and edit any Employee, such as someone in HR.
 */
function employeeWhoManagesEmployees(): Employee
{
    return employeeWith([Permission::AddEmployees->value => null, Permission::ManageEmployees->value => Scope::Everyone]);
}

/**
 * Ada, who manages Roles through a Role with that one Permission, named
 * Admin unless told otherwise.
 */
function employeeWhoManagesRoles(string $role = 'Admin'): Employee
{
    $ada = Employee::factory()->create(['name' => 'Ada Admin']);
    $ada->roles()->attach(Role::factory()->withPermissions([Permission::ManageRoles->value => null])->create(['name' => $role]));

    return $ada;
}

/**
 * An Employee holding the Admin Role, which holds every Permission with
 * the everyone Scope.
 */
function employeeHoldingAdmin(): Employee
{
    $admin = Employee::factory()->create();
    $admin->roles()->attach(Role::factory()->admin()->create());

    return $admin;
}

/**
 * Ben, whose own Manager is Ada, ready to be given Reports of his own whose
 * Work Entries and Time Off he decides. No Role is involved.
 */
function managerBen(): Employee
{
    $ada = Employee::factory()->create(['name' => 'Ada']);

    return Employee::factory()->reportingTo($ada)->create(['name' => 'Ben']);
}

/**
 * Give an Employee a further Role with the given Permissions.
 *
 * @param  array<string, Scope|null>  $grants
 */
function giveRole(Employee $employee, array $grants): Role
{
    $role = Role::factory()->withPermissions($grants)->create();

    $employee->roles()->attach($role);

    return $role;
}

/**
 * An Employee's record as the edit form sends it, with some changes.
 *
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function employeeRecordChanging(Employee $employee, array $changes): array
{
    return [
        'name' => $employee->name,
        'email' => $employee->email,
        'department_id' => $employee->department_id,
        'manager_id' => $employee->manager_id,
        'start_date' => $employee->start_date->toDateString(),
        'leaving_date' => $employee->leaving_date?->toDateString(),
        ...$changes,
    ];
}

/**
 * An Employee named Cara who records their own hours, as the starter
 * Employee Role allows, reporting to Mona, who could approve them.
 *
 * @param  array<string, mixed>  $attributes
 */
function recorder(array $attributes = []): Employee
{
    return employeeWith([Permission::ManageWorkEntries->value => Scope::Own], ['name' => 'Cara', 'start_date' => '2025-01-06', 'manager_id' => fn () => Employee::factory()->startedLongAgo()->create(['name' => 'Mona'])->id, ...$attributes]);
}

/**
 * The Employee's Assignment to a Project, running from 2 Nov 2026 unless
 * told otherwise.
 *
 * @param  array<string, mixed>  $attributes
 */
function assignmentOf(Employee $employee, string $project = 'Relaunch', array $attributes = []): Assignment
{
    return Assignment::factory()->for($employee)->for(Project::factory()->create(['name' => $project, 'start_date' => '2026-01-05']))
        ->create(['start_date' => '2026-11-02', 'end_date' => null, ...$attributes]);
}

/**
 * A Work Entry as the add form sends it, with some changes.
 *
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function workEntryForm(Assignment $assignment, array $changes = []): array
{
    return ['assignment_id' => $assignment->id, 'date' => '2026-11-10', 'hours' => '3', 'description' => 'Reviewing the API', ...$changes];
}

/**
 * Types a date into a DatePicker's month, day and year, as someone using
 * the keyboard would. The field is the selector of the picker's `id`, such
 * as "#period-from"; the date is YYYY-MM-DD.
 */
function pickDate(AwaitableWebpage|PendingAwaitablePage|Webpage $page, string $field, string $date): AwaitableWebpage|PendingAwaitablePage|Webpage
{
    typeDateSegments($page, "[role=\"group\"]:has(> {$field})", $date);
    $page->assertValue($field, $date);

    return $page;
}

/**
 * Types a first and a last date into a DateRangePicker, as someone using the
 * keyboard would. The field is the selector of the picker's `id`; the dates
 * are YYYY-MM-DD.
 */
function pickDateRange(AwaitableWebpage|PendingAwaitablePage|Webpage $page, string $field, string $start, string $end): AwaitableWebpage|PendingAwaitablePage|Webpage
{
    $group = "[role=\"group\"]:has(> {$field})";

    typeDateSegments($page, $group, $start, '[data-reka-date-range-field-segment-type="start"]');

    return typeDateSegments($page, $group, $end, '[data-reka-date-range-field-segment-type="end"]');
}

/**
 * Types a YYYY-MM-DD date into the month, day and year segments inside a
 * date field, those of one end of a range when a segment filter is given.
 * Under load a keystroke can land after the field has moved focus on by
 * itself, so each segment is cleared, typed, and typed again until it
 * holds its number, a few tries at most; a segment that never takes it
 * fails the test.
 */
function typeDateSegments(AwaitableWebpage|PendingAwaitablePage|Webpage $page, string $group, string $date, string $segmentFilter = ''): AwaitableWebpage|PendingAwaitablePage|Webpage
{
    [$year, $month, $day] = explode('-', $date);

    foreach (['month' => $month, 'day' => $day, 'year' => $year] as $part => $digits) {
        $segment = "{$group} [data-reka-date-field-segment=\"{$part}\"]{$segmentFilter}";
        $attempts = 0;

        do {
            $page->keys($segment, ['Backspace', 'Backspace', 'Backspace', 'Backspace', ...str_split($digits)]);
            $typed = $page->script('document.querySelector('.json_encode($segment).")?.getAttribute('aria-valuenow')");
        } while ((int) $typed !== (int) $digits && ++$attempts < 3);

        expect((int) $typed)->toBe((int) $digits, "The {$part} of {$date} didn't take after {$attempts} retries.");
    }

    return $page;
}

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
