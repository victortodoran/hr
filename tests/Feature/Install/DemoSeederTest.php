<?php

use App\Enums\EmploymentStatus;
use App\Models\AccessLogEntry;
use App\Models\Assignment;
use App\Models\Client;
use App\Models\CompanySettings;
use App\Models\Department;
use App\Models\Employee;
use App\Models\PayEntry;
use App\Models\Project;
use App\Models\Role;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\post;
use function Pest\Laravel\seed;

covers(DemoSeeder::class);

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

it('lets the demo Admin sign in with the demo password', function () {
    seed(DemoSeeder::class);

    post(route('login.store'), ['email' => 'ana@acme.test', 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));
});

it('leaves an installed company alone', function () {
    Notification::fake();
    seed(DemoSeeder::class);

    seed(DemoSeeder::class);

    expect(CompanySettings::query()->count())->toBe(1)
        ->and(Employee::query()->where('email', 'ana@acme.test')->count())->toBe(1);
});
