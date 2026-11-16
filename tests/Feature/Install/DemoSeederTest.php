<?php

use App\Enums\EmploymentStatus;
use App\Enums\Permission;
use App\Enums\TimeOffState;
use App\Enums\TravelRequestState;
use App\Enums\WorkEntryState;
use App\Models\AccessLogEntry;
use App\Models\Assignment;
use App\Models\Builders\AssignmentBuilder;
use App\Models\Client;
use App\Models\CompanySettings;
use App\Models\Department;
use App\Models\Employee;
use App\Models\PayEntry;
use App\Models\Project;
use App\Models\Role;
use App\Models\TimeOff;
use App\Models\TravelRequest;
use App\Models\WorkEntry;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\post;
use function Pest\Laravel\seed;

it('seeds a demo company with at least one of everything to try by hand', function () {
    seed(DemoSeeder::class);

    expect(CompanySettings::query()->count())->toBe(1)
        ->and(Department::query()->count())->toBeGreaterThan(1)
        ->and(Role::query()->pluck('name')->all())->toContain('Admin', 'Manager', 'Employee', 'Senior Manager', 'Payroll')
        ->and(Client::query()->count())->toBeGreaterThan(1)
        ->and(Project::query()->where('is_internal', false)->count())->toBeGreaterThan(1)
        ->and(Assignment::query()->notInternal()->count())->toBeGreaterThan(1)
        ->and(PayEntry::query()->where('effective_from', '>', today())->exists())->toBeTrue()
        ->and(AccessLogEntry::query()->exists())->toBeTrue()
        ->and(Employee::query()->get()->map(fn (Employee $employee): EmploymentStatus => $employee->employmentStatus())->unique()->values()->all())
        ->toEqualCanonicalizing([EmploymentStatus::Future, EmploymentStatus::Current, EmploymentStatus::Former]);
});

it('gives Ben Pending Work Entries of the Employees he manages to approve, next to ones he Approved or Declined and Locked ones', function () {
    seed(DemoSeeder::class);
    $ben = Employee::query()->where('email', 'ben@acme.test')->sole();
    $managedByBen = Employee::query()->effectivelyManagedBy($ben)->pluck('id');

    $pending = WorkEntry::query()->where('state', WorkEntryState::Pending)->with('employee')->get();

    expect($pending->pluck('employee.name')->unique()->count())->toBeGreaterThan(1)
        ->and($pending->every(fn (WorkEntry $entry): bool => $entry->date->lte(today())))->toBeTrue()
        ->and($pending->filter(fn (WorkEntry $entry): bool => $managedByBen->contains($entry->employee->id))->count())->toBeGreaterThan(1)
        ->and(WorkEntry::query()->where('state', WorkEntryState::Approved)->where('approved_by', $ben->id)->exists())->toBeTrue()
        ->and(WorkEntry::query()->where('state', WorkEntryState::Locked)->exists())->toBeTrue()
        ->and(WorkEntry::query()->where('state', WorkEntryState::Declined)->where('declined_by', $ben->id)->whereNotNull('decline_reason')->exists())->toBeTrue();
});

it('gives most Employees Paid Days and one none, and Time Off in every state for their Managers to see', function () {
    seed(DemoSeeder::class);
    $paidDays = Employee::query()->pluck('paid_days');
    $ben = Employee::query()->where('email', 'ben@acme.test')->sole();
    $pendingForBen = TimeOff::query()->where('state', TimeOffState::Pending)
        ->whereIn('employee_id', Employee::query()->effectivelyManagedBy($ben)->select('employees.id'));

    expect($paidDays->filter(fn (int $days): bool => $days > 0)->count())->toBeGreaterThan(intdiv($paidDays->count(), 2))
        ->and($paidDays)->toContain(0)
        ->and(TimeOff::query()->pluck('state')->unique()->values()->all())->toEqualCanonicalizing(TimeOffState::cases())
        ->and(TimeOff::query()->where('state', TimeOffState::Declined)->whereNull('decline_reason')->exists())->toBeFalse()
        ->and(TimeOff::query()->where('is_paid', false)->exists())->toBeTrue()
        ->and($pendingForBen->exists())->toBeTrue()
        ->and(Employee::query()->get()->every(fn (Employee $employee): bool => $employee->paidDaysLeftIn(today()->year) >= 0))->toBeTrue()
        ->and($ben->can(Permission::ViewTimeOff->value, $pendingForBen->firstOrFail()->employee))->toBeTrue();
});

it('gives Travel Requests in every state, on Client and Internal Assignments, sharing no day with other standing Travel Requests or Time Off', function () {
    seed(DemoSeeder::class);
    $standing = TravelRequest::query()->standing()->get();

    expect(TravelRequest::query()->pluck('state')->unique()->values()->all())->toEqualCanonicalizing(TravelRequestState::cases())
        ->and(TravelRequest::query()->where('state', TravelRequestState::Declined)->whereNull('decline_reason')->exists())->toBeFalse()
        ->and(TravelRequest::query()->whereHas('assignment', fn (AssignmentBuilder $assignments) => $assignments->internal())->exists())->toBeTrue()
        ->and(TravelRequest::query()->whereHas('assignment', fn (AssignmentBuilder $assignments) => $assignments->notInternal())->exists())->toBeTrue()
        ->and($standing->every(fn (TravelRequest $travel): bool => $travel->employee->travelRequests()->standing()->overlapping($travel->first_date, $travel->last_date)->count() === 1
            && ! $travel->employee->timeOff()->standing()->overlapping($travel->first_date, $travel->last_date)->exists()))->toBeTrue();
});

it('lets the demo Admin sign in with the demo password', function () {
    seed(DemoSeeder::class);

    post(route('login.store'), ['email' => 'ana@acme.test', 'password' => 'password'])
        ->assertRedirect(route('landing', absolute: false));
});

it('leaves an installed company alone', function () {
    Notification::fake();
    seed(DemoSeeder::class);

    seed(DemoSeeder::class);

    expect(CompanySettings::query()->count())->toBe(1)
        ->and(Employee::query()->where('email', 'ana@acme.test')->count())->toBe(1);
});
