<?php

use App\Actions\AddEmployee;
use App\Actions\InstallCompany;
use App\Enums\PayKind;
use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\Assignments\AssignmentController;
use App\Http\Controllers\Assignments\PayEntryController;
use App\Http\Controllers\Employees\EmployeeController;
use App\Http\Requests\Assignments\PayEntryRequest;
use App\Models\Assignment;
use App\Models\Client;
use App\Models\CompanySettings;
use App\Models\Department;
use App\Models\Employee;
use App\Models\PayEntry;
use App\Models\Project;
use App\Policies\AssignmentPolicy;
use App\Support\Currency;
use App\Support\PayHistory;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(AssignmentController::class, EmployeeController::class, PayEntryController::class, PayEntryRequest::class, AssignmentPolicy::class, PayHistory::class, Currency::class, CompanySettings::class, PayEntry::class, Assignment::class);

beforeEach(function () {
    travelTo('2026-03-01 09:00');
    CompanySettings::factory()->create(['currency' => 'EUR']);
});

/**
 * An Employee who may manage everyone's Pay, such as someone in Payroll.
 */
function payManager(): Employee
{
    return employeeWith([Permission::ManagePay->value => Scope::Everyone]);
}

/**
 * Cara's Assignment to Relaunch, running through 2026.
 */
function relaunchAssignment(): Assignment
{
    $cara = Employee::factory()->startedLongAgo()->create(['name' => 'Cara']);

    return Assignment::factory()->for($cara)->for(Project::factory()->create(['name' => 'Relaunch', 'start_date' => '2026-01-05']))
        ->create(['start_date' => '2026-02-01', 'end_date' => '2026-12-31']);
}

/**
 * A Pay entry as the add form sends it, with some changes.
 *
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function payForm(array $changes = []): array
{
    return ['kind' => 'fixed', 'amount' => '4500', 'effective_from' => '2026-02-01', ...$changes];
}

it('lets someone who manages Pay give an Assignment Fixed or Hourly Pay, from any day while it runs, including ahead of time', function (string $kind, string $amount, string $effectiveFrom, string $shown) {
    $assignment = relaunchAssignment();
    $manager = payManager();

    actingAs($manager)
        ->post(route('assignments.pay.store', $assignment), payForm(['kind' => $kind, 'amount' => $amount, 'effective_from' => $effectiveFrom]))
        ->assertRedirect(route('employees.show', ['employee' => $assignment->employee_id, 'tab' => 'assignments']))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Pay added.']);

    actingAs($manager)->get(route('employees.show', ['employee' => $assignment->employee_id, 'tab' => 'assignments']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('assignments.0.pay', [
                ['id' => $assignment->payEntries()->sole()->id, 'effective_from' => $effectiveFrom, 'until' => '2026-12-31', 'kind' => $kind, 'amount' => $shown, 'state' => $effectiveFrom <= '2026-03-01' ? 'in_effect' : 'upcoming'],
            ]));
})->with([
    'Fixed Pay from the first day' => ['fixed', '4500.50', '2026-02-01', '€4,500.50'],
    'Hourly Pay from today' => ['hourly', '22.75', '2026-03-01', '€22.75'],
    'a raise on the last day' => ['fixed', '5000', '2026-12-31', '€5,000.00'],
]);

it('lets the Internal Assignment have either kind of Pay', function (string $kind) {
    $internal = Assignment::factory()->internal()->for(Employee::factory()->startedLongAgo()->create())->create();

    actingAs(payManager())
        ->post(route('assignments.pay.store', $internal), payForm(['kind' => $kind, 'effective_from' => '2025-01-06']))
        ->assertSessionHasNoErrors();

    expect($internal->payEntries()->sole()->kind)->toBe(PayKind::from($kind));
})->with(['fixed', 'hourly']);

it('shows the Pay history newest first, each change applying until the day before the next one, as Upcoming, in effect on the day, or Past', function (string $day, array $states) {
    $assignment = relaunchAssignment();
    PayEntry::factory()->for($assignment)->create(['effective_from' => '2026-02-01', 'kind' => PayKind::Fixed, 'amount' => 400000]);
    PayEntry::factory()->for($assignment)->hourly()->create(['effective_from' => '2026-06-01', 'amount' => 3000]);
    PayEntry::factory()->for($assignment)->create(['effective_from' => '2026-09-01', 'kind' => PayKind::Fixed, 'amount' => 450000]);
    travelTo($day);

    actingAs(payManager())->get(route('employees.show', ['employee' => $assignment->employee_id, 'tab' => 'assignments']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('assignments.0.pay.0.effective_from', '2026-09-01')
            ->where('assignments.0.pay.0.amount', '€4,500.00')
            ->where('assignments.0.pay.1.kind', 'hourly')
            ->where('assignments.0.pay.1.amount', '€30.00')
            ->where('assignments.0.pay.2.effective_from', '2026-02-01')
            ->where('assignments.0.pay.0.until', '2026-12-31')
            ->where('assignments.0.pay.1.until', '2026-08-31')
            ->where('assignments.0.pay.2.until', '2026-05-31')
            ->where('assignments.0.pay.0.state', $states[0])
            ->where('assignments.0.pay.1.state', $states[1])
            ->where('assignments.0.pay.2.state', $states[2]));
})->with([
    'from the first entry' => ['2026-03-01', ['upcoming', 'upcoming', 'in_effect']],
    'once the raise takes effect' => ['2026-06-01', ['upcoming', 'in_effect', 'past']],
    'the day before the planned raise' => ['2026-08-31', ['upcoming', 'in_effect', 'past']],
    'after the last entry' => ['2026-10-15', ['in_effect', 'past', 'past']],
]);

it('lets the latest Pay change of an open-ended Assignment apply with no last day', function () {
    $cara = Employee::factory()->startedLongAgo()->create();
    $assignment = Assignment::factory()->for($cara)->create(['start_date' => '2026-02-01', 'end_date' => null]);
    PayEntry::factory()->for($assignment)->create(['effective_from' => '2026-02-01']);

    actingAs(payManager())->get(route('employees.show', ['employee' => $cara, 'tab' => 'assignments']))
        ->assertInertia(fn (Assert $page) => $page->where('assignments.0.pay.0.until', null));
});

it('sums up the Pay in effect today on each Assignment running today, leaving out those not started yet or ended', function () {
    $cara = Employee::factory()->create(['start_date' => '2025-01-06']);
    $internal = Assignment::factory()->internal()->for($cara)->create();
    PayEntry::factory()->for($internal)->create(['effective_from' => '2025-01-06', 'kind' => PayKind::Fixed, 'amount' => 480000]);
    $relaunch = Assignment::factory()->for($cara)->for(Project::factory()->create(['name' => 'Relaunch', 'start_date' => '2025-01-06']))
        ->create(['start_date' => '2026-01-05', 'end_date' => null, 'capacity' => 60]);
    PayEntry::factory()->for($relaunch)->create(['effective_from' => '2026-01-05', 'kind' => PayKind::Hourly, 'amount' => 2500]);
    PayEntry::factory()->for($relaunch)->create(['effective_from' => '2026-06-01', 'kind' => PayKind::Hourly, 'amount' => 2800]);
    $later = Assignment::factory()->for($cara)->create(['start_date' => '2026-04-01', 'end_date' => null, 'capacity' => 20]);
    PayEntry::factory()->for($later)->create(['effective_from' => '2026-04-01']);
    $ended = Assignment::factory()->for($cara)->create(['start_date' => '2025-02-01', 'end_date' => '2026-02-28', 'capacity' => 30]);
    PayEntry::factory()->for($ended)->create(['effective_from' => '2025-02-01']);

    actingAs(payManager())->get(route('employees.show', ['employee' => $cara, 'tab' => 'assignments']))
        ->assertInertia(fn (Assert $page) => $page->where('assignmentStats', [
            'capacity_assigned' => 60,
            'active' => 2,
            'ended' => 1,
            'pay_in_effect' => [
                ['project' => $internal->project->name, 'is_internal' => true, 'kind' => 'fixed', 'amount' => '€4,800.00'],
                ['project' => 'Relaunch', 'is_internal' => false, 'kind' => 'hourly', 'amount' => '€25.00'],
            ],
        ]));
});

it('shows an Assignment with no Pay yet as having none', function () {
    $assignment = relaunchAssignment();

    actingAs(payManager())->get(route('employees.show', ['employee' => $assignment->employee_id, 'tab' => 'assignments']))
        ->assertInertia(fn (Assert $page) => $page->where('assignments.0.pay', []));
});

it('rejects an invalid Pay entry and explains why', function (Closure $changes, string $field, string $message) {
    $assignment = relaunchAssignment();
    PayEntry::factory()->for($assignment)->create(['effective_from' => '2026-05-01']);

    actingAs(payManager())
        ->post(route('assignments.pay.store', $assignment), payForm($changes()))
        ->assertSessionHasErrors([$field => $message]);

    expect($assignment->payEntries()->count())->toBe(1);
})->with([
    'kind missing' => [fn (): array => ['kind' => ''], 'kind', 'The kind field is required.'],
    'unknown kind' => [fn (): array => ['kind' => 'yearly'], 'kind', 'The selected kind is invalid.'],
    'amount missing' => [fn (): array => ['amount' => ''], 'amount', 'The amount field is required.'],
    'amount not a number' => [fn (): array => ['amount' => 'lots'], 'amount', 'The amount field must have 0-2 decimal places.'],
    'amount of nothing' => [fn (): array => ['amount' => '0'], 'amount', 'The amount field must be greater than 0.'],
    'negative amount' => [fn (): array => ['amount' => '-10'], 'amount', 'The amount field must be greater than 0.'],
    'more decimals than the currency has' => [fn (): array => ['amount' => '4500.505'], 'amount', 'The amount field must have 0-2 decimal places.'],
    'amount too large' => [fn (): array => ['amount' => '1000000000'], 'amount', 'The amount field must not be greater than 999999999.'],
    'date missing' => [fn (): array => ['effective_from' => ''], 'effective_from', 'The effective date field is required.'],
    'not a date' => [fn (): array => ['effective_from' => 'soon'], 'effective_from', 'The effective date field must be a valid date.'],
    'before the Assignment starts' => [fn (): array => ['effective_from' => '2026-01-31'], 'effective_from', 'Pay must take effect while the Assignment runs, from 2026-02-01 to 2026-12-31.'],
    'after the Assignment ends' => [fn (): array => ['effective_from' => '2027-01-01'], 'effective_from', 'Pay must take effect while the Assignment runs, from 2026-02-01 to 2026-12-31.'],
    'the same day as another entry' => [fn (): array => ['effective_from' => '2026-05-01'], 'effective_from', 'This Assignment already has Pay taking effect on that day.'],
]);

it('lets Pay take effect any day from the start of an open-ended Assignment', function () {
    $assignment = relaunchAssignment();
    $assignment->update(['end_date' => null]);

    actingAs(payManager())
        ->post(route('assignments.pay.store', $assignment), payForm(['effective_from' => '2030-01-01']))
        ->assertSessionHasNoErrors();

    actingAs(payManager())
        ->post(route('assignments.pay.store', $assignment), payForm(['effective_from' => '2026-01-31']))
        ->assertSessionHasErrors(['effective_from' => 'Pay must take effect while the Assignment runs, from 2026-02-01 with no end date.']);
});

it('takes as many decimals as the company currency has', function () {
    CompanySettings::query()->update(['currency' => 'JPY']);
    $assignment = relaunchAssignment();

    actingAs(payManager())
        ->post(route('assignments.pay.store', $assignment), payForm(['amount' => '5000.5']))
        ->assertSessionHasErrors(['amount' => 'The amount field must have 0 decimal places.']);

    actingAs(payManager())
        ->post(route('assignments.pay.store', $assignment), payForm(['amount' => '5000']))
        ->assertSessionHasNoErrors();

    expect($assignment->payEntries()->sole()->amount)->toBe(5000);
});

it('lets someone who manages Pay remove an entry, such as a mistake', function () {
    $assignment = relaunchAssignment();
    $kept = PayEntry::factory()->for($assignment)->create(['effective_from' => '2026-02-01']);
    $mistake = PayEntry::factory()->for($assignment)->create(['effective_from' => '2026-04-01']);

    actingAs(payManager())
        ->delete(route('assignments.pay.destroy', [$assignment, $mistake]))
        ->assertRedirect(route('employees.show', ['employee' => $assignment->employee_id, 'tab' => 'assignments']))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Pay removed.']);

    expect($assignment->payEntries()->pluck('id')->all())->toBe([$kept->id]);
});

it('only removes a Pay entry through its own Assignment', function () {
    $assignment = relaunchAssignment();
    $elsewhere = PayEntry::factory()->create();

    actingAs(payManager())
        ->delete(route('assignments.pay.destroy', [$assignment, $elsewhere]))
        ->assertNotFound();

    expect($elsewhere->fresh())->not->toBeNull();
});

it('sends Pay only to viewers whose view pay or manage pay covers the Employee', function (Closure $viewer, bool $seesPay, bool $managesPay) {
    $ben = Employee::factory()->create(['name' => 'Ben']);
    $cara = Employee::factory()->reportingTo($ben)->startedLongAgo()->create(['name' => 'Cara']);
    PayEntry::factory()->for(Assignment::factory()->for($cara))->create();

    $page = actingAs($viewer($ben))->get(route('employees.show', ['employee' => $cara, 'tab' => 'assignments']))->assertOk();

    $page->assertInertia(fn (Assert $page) => $page
        ->where('canViewPay', $seesPay)
        ->where('canManagePay', $managesPay)
        ->when($seesPay, fn (Assert $page) => $page->has('assignments.0.pay', 1)->has('assignmentStats.pay_in_effect'))
        ->when($managesPay, fn (Assert $page) => $page->where('currency', 'EUR'))
        ->when(! $managesPay, fn (Assert $page) => $page->missing('currency'))
        ->when(! $seesPay, fn (Assert $page) => $page->missing('assignments.0.pay')->missing('assignmentStats.pay_in_effect')->missing('currency')));
})->with([
    'a Manager without view pay' => [fn (Employee $ben): Employee => tap($ben, fn () => giveRole($ben, [Permission::ViewAssignments->value => Scope::Reports, Permission::ManageAssignments->value => Scope::Reports])), false, false],
    'view pay for their Reports' => [fn (Employee $ben): Employee => tap($ben, fn () => giveRole($ben, [Permission::ViewAssignments->value => Scope::Reports, Permission::ViewPay->value => Scope::Reports])), true, false],
    'view pay only for themselves' => [fn (Employee $ben): Employee => tap($ben, fn () => giveRole($ben, [Permission::ViewAssignments->value => Scope::Everyone, Permission::ViewPay->value => Scope::Own])), false, false],
    'a Payroll Role with only view pay' => [fn (): Employee => employeeWith([Permission::ViewPay->value => Scope::Everyone]), true, false],
    'manage pay, which includes viewing it' => [fn (): Employee => employeeWith([Permission::ManagePay->value => Scope::Everyone]), true, true],
]);

it('refuses to add or remove Pay without manage pay covering the Employee', function (Closure $grants) {
    $assignment = relaunchAssignment();
    $entry = PayEntry::factory()->for($assignment)->create();
    $viewer = employeeWith($grants());

    actingAs($viewer)->post(route('assignments.pay.store', $assignment), payForm(['effective_from' => '2026-04-01']))->assertForbidden();
    actingAs($viewer)->delete(route('assignments.pay.destroy', [$assignment, $entry]))->assertForbidden();

    expect($assignment->payEntries()->pluck('id')->all())->toBe([$entry->id]);
})->with([
    'view pay only' => [fn (): array => [Permission::ViewPay->value => Scope::Everyone]],
    'manage assignments' => [fn (): array => [Permission::ManageAssignments->value => Scope::Everyone]],
    'manage pay for themselves' => [fn (): array => [Permission::ManagePay->value => Scope::Own]],
]);

it('lets a new Employee see their own Pay through the starter Employee Role', function () {
    Notification::fake();
    CompanySettings::query()->delete();
    $admin = app(InstallCompany::class)->install('Acme', 'EUR', 'Engineering', 'Ana', 'ana@acme.test');
    $cara = app(AddEmployee::class)->add($admin, [
        'name' => 'Cara', 'email' => 'cara@acme.test', 'department_id' => Department::query()->value('id'),
        'manager_id' => $admin->id, 'start_date' => '2026-02-01', 'leaving_date' => null, 'job_title' => 'Developer',
    ]);
    $cara->forceFill(['email_verified_at' => now()])->save(); // as accepting the invitation does
    PayEntry::factory()->for($cara->internalAssignment()->sole())->create(['amount' => 350000]);

    actingAs($cara)->get(route('employees.show', ['employee' => $cara, 'tab' => 'assignments']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canViewPay', true)
            ->where('canManagePay', false)
            ->where('assignments.0.pay.0.amount', '€3,500.00'));
});

it('never sends Pay on a Project\'s team or a Client\'s page', function () {
    $assignment = relaunchAssignment();
    $assignment->project->update(['client_id' => Client::factory()->create()->id]);
    PayEntry::factory()->for($assignment)->create();
    $viewer = employeeWith([
        Permission::ManageProjects->value => null,
        Permission::ManageClients->value => null,
        Permission::ViewAssignments->value => Scope::Everyone,
        Permission::ManagePay->value => Scope::Everyone,
    ]);

    actingAs($viewer)->get(route('projects.show', $assignment->project_id))
        ->assertInertia(fn (Assert $page) => $page->has('team.0')->missing('team.0.pay'));
    actingAs($viewer)->get(route('clients.show', $assignment->project->client_id))
        ->assertInertia(fn (Assert $page) => $page->has('projects.0.team.0')->missing('projects.0.team.0.pay'));
});
