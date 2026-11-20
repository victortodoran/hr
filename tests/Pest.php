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
 * Each run empties the log of the real exceptions behind failing requests, so
 * it only holds the latest run; `composer test` empties it before its parallel
 * workers start.
 */
if (getenv('TEST_TOKEN') === false) {
    file_put_contents(__DIR__.'/../storage/logs/testing.log', '');
}

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(BrowserTestCase::class)->use(RefreshDatabase::class)->in('Browser');

/* Waits up to 10 seconds, so a busy parallel run doesn't fail a page that is only slow to settle. */
pest()->browser()->timeout(10_000);

/**
 * A null Scope marks a company-wide Permission.
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

function employeeWhoManagesEmployees(): Employee
{
    return employeeWith([Permission::AddEmployees->value => null, Permission::ManageEmployees->value => Scope::Everyone]);
}

function employeeWhoManagesRoles(string $role = 'Admin'): Employee
{
    $ada = Employee::factory()->create(['name' => 'Ada Admin']);
    $ada->roles()->attach(Role::factory()->withPermissions([Permission::ManageRoles->value => null])->create(['name' => $role]));

    return $ada;
}

function employeeHoldingAdmin(): Employee
{
    $admin = Employee::factory()->create();
    $admin->roles()->attach(Role::factory()->admin()->create());

    return $admin;
}

function managerBen(): Employee
{
    $ada = Employee::factory()->create(['name' => 'Ada']);

    return Employee::factory()->reportingTo($ada)->create(['name' => 'Ben']);
}

/** @param  array<string, Scope|null>  $grants */
function giveRole(Employee $employee, array $grants): Role
{
    $role = Role::factory()->withPermissions($grants)->create();

    $employee->roles()->attach($role);

    return $role;
}

/**
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

/** @param  array<string, mixed>  $attributes */
function recorder(array $attributes = []): Employee
{
    return employeeWith([Permission::ManageWorkEntries->value => Scope::Own], ['name' => 'Cara', 'start_date' => '2025-01-06', 'manager_id' => fn () => Employee::factory()->startedLongAgo()->create(['name' => 'Mona'])->id, ...$attributes]);
}

/** @param  array<string, mixed>  $attributes */
function assignmentOf(Employee $employee, string $project = 'Relaunch', array $attributes = []): Assignment
{
    return Assignment::factory()->for($employee)->for(Project::factory()->create(['name' => $project, 'start_date' => '2026-01-05']))
        ->create(['start_date' => '2026-11-02', 'end_date' => null, ...$attributes]);
}

/**
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function workEntryForm(Assignment $assignment, array $changes = []): array
{
    return ['assignment_id' => $assignment->id, 'date' => '2026-11-10', 'hours' => '3', 'description' => 'Reviewing the API', ...$changes];
}

/** Rule P5: a page fits a 390px phone without sideways scrolling. */
function fitsScreenWidth(): string
{
    return 'document.documentElement.scrollWidth <= window.innerWidth';
}

/** The field is the selector of the picker's `id`, such as "#period-from"; the date is YYYY-MM-DD. */
function pickDate(AwaitableWebpage|PendingAwaitablePage|Webpage $page, string $field, string $date): AwaitableWebpage|PendingAwaitablePage|Webpage
{
    typeDateSegments($page, "[role=\"group\"]:has(> {$field})", $date);
    $page->assertValue($field, $date);

    return $page;
}

/** The field is the selector of the picker's `id`; the dates are YYYY-MM-DD. */
function pickDateRange(AwaitableWebpage|PendingAwaitablePage|Webpage $page, string $field, string $start, string $end): AwaitableWebpage|PendingAwaitablePage|Webpage
{
    $group = "[role=\"group\"]:has(> {$field})";

    typeDateSegments($page, $group, $start, '[data-reka-date-range-field-segment-type="start"]');

    return typeDateSegments($page, $group, $end, '[data-reka-date-range-field-segment-type="end"]');
}

/**
 * Under load a keystroke can land after the field has moved focus on by
 * itself, so each segment is cleared and retyped until it holds its number,
 * a few tries at most.
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
 * @param  TestResponse<Response>  $response
 * @return list<array<int, mixed>>
 */
function csvRows(TestResponse $response): array
{
    return downloadedRows($response, new CsvReader);
}

/**
 * @param  TestResponse<Response>  $response
 * @return list<array<int, mixed>>
 */
function xlsxRows(TestResponse $response): array
{
    return downloadedRows($response, new XlsxReader);
}

/**
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
