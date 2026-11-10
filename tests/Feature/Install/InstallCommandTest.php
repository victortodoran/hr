<?php

use App\Actions\InstallCompany;
use App\Console\Commands\InstallCommand;
use App\Enums\Permission;
use App\Models\CompanySettings;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use App\Notifications\Invitation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\PendingCommand;

use function Pest\Laravel\artisan;

covers(InstallCommand::class, InstallCompany::class);

/**
 * Run hr:install with the given options and return the pending command
 * so its outcome can be asserted.
 *
 * @param  array<string, string>  $options
 */
function install(array $options = []): PendingCommand
{
    $command = artisan('hr:install', $options);

    if (! $command instanceof PendingCommand) {
        throw new LogicException('Console mocking must be enabled to assert on hr:install.');
    }

    return $command;
}

/**
 * @return array<string, string>
 */
function installOptions(): array
{
    return [
        '--company' => 'Acme Software',
        '--currency' => 'eur',
        '--department' => 'Operations',
        '--name' => 'Dana Owner',
        '--email' => 'dana@acme.test',
    ];
}

beforeEach(fn () => Notification::fake());

it('sets up the company, first Department and top Employee', function () {
    install(installOptions())->assertSuccessful();

    $dana = Employee::query()->where('email', 'dana@acme.test')->sole();

    expect($dana->name)->toBe('Dana Owner')
        ->and($dana->is_top)->toBeTrue()
        ->and($dana->department?->name)->toBe('Operations')
        ->and($dana->hasVerifiedEmail())->toBeFalse();

    $settings = CompanySettings::current();

    expect($settings?->name)->toBe('Acme Software')
        ->and($settings?->currency)->toBe('EUR');
});

it('invites the first Employee to set their password', function () {
    install(installOptions())->assertSuccessful();

    Notification::assertSentTo(Employee::query()->where('email', 'dana@acme.test')->sole(), Invitation::class);
});

it('gives the first Employee the Admin Role, able to do everything to everyone', function () {
    install(installOptions())->assertSuccessful();

    $dana = Employee::query()->where('email', 'dana@acme.test')->sole();
    $colleague = Employee::factory()->create();

    foreach (Permission::cases() as $permission) {
        $allowed = $permission->isScoped()
            ? Gate::forUser($dana)->allows($permission->value, $colleague)
            : Gate::forUser($dana)->allows($permission->value);

        expect($allowed)->toBeTrue("Admin should hold {$permission->value}");
    }
});

it('creates the starter Employee Role: directory, plus own records, Assignments and Pay', function () {
    install(installOptions())->assertSuccessful();

    $cara = Employee::factory()->create();
    $cara->roles()->attach(Role::query()->where('name', 'Employee')->sole());
    $colleague = Employee::factory()->create();
    $gate = Gate::forUser($cara);

    expect($gate->allows(Permission::ViewDirectory->value))->toBeTrue()
        ->and($gate->allows(Permission::ViewEmployeeRecords->value, $cara))->toBeTrue()
        ->and($gate->allows(Permission::ViewAssignments->value, $cara))->toBeTrue()
        ->and($gate->allows(Permission::ViewPay->value, $cara))->toBeTrue()
        ->and($gate->allows(Permission::ViewEmployeeRecords->value, $colleague))->toBeFalse()
        ->and($gate->allows(Permission::ManageEmployees->value, $cara))->toBeFalse()
        ->and($gate->allows(Permission::ManageRoles->value))->toBeFalse();
});

it('creates the starter Manager Role: Reports\' records and Assignments, plus Projects', function () {
    install(installOptions())->assertSuccessful();

    $ben = Employee::factory()->create();
    $ben->roles()->attach(Role::query()->where('name', 'Manager')->sole());
    $gate = Gate::forUser($ben);

    expect($gate->allows(Permission::ViewProjects->value))->toBeTrue()
        ->and($ben->widestScopeFor(Permission::ViewEmployeeRecords)?->value)->toBe('reports')
        ->and($ben->widestScopeFor(Permission::ViewAssignments)?->value)->toBe('reports')
        ->and($ben->widestScopeFor(Permission::ManageAssignments)?->value)->toBe('reports')
        ->and($ben->widestScopeFor(Permission::ViewPay))->toBeNull()
        ->and($gate->allows(Permission::ManageProjects->value))->toBeFalse();
});

it('refuses to run on an app that is already installed', function () {
    install(installOptions())->assertSuccessful();

    install([...installOptions(), '--email' => 'someone@acme.test'])
        ->expectsOutputToContain('already installed')
        ->assertFailed();

    expect(Employee::query()->count())->toBe(1)
        ->and(Department::query()->count())->toBe(1);
});

it('refuses to run when Employees already exist, even without company settings', function () {
    Employee::factory()->create();

    install(installOptions())->expectsOutputToContain('already installed')->assertFailed();

    expect(CompanySettings::current())->toBeNull();
});

it('rejects invalid values and explains why', function (string $option, string $value, string $message) {
    install([...installOptions(), $option => $value])
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect(Employee::query()->count())->toBe(0)
        ->and(CompanySettings::current())->toBeNull();
})->with([
    'currency not three letters' => ['--currency', 'euro', 'three-letter code'],
    'currency with a digit' => ['--currency', 'E1R', 'three-letter code'],
    'invalid email' => ['--email', 'not-an-email', 'valid email'],
    'company name too long' => ['--company', str_repeat('a', 256), 'company'],
    'Department name too long' => ['--department', str_repeat('a', 256), 'department'],
    'full name too long' => ['--name', str_repeat('a', 256), 'name'],
    'email too long' => ['--email', str_repeat('a', 250).'@acme.test', 'email'],
]);

it('requires an answer to every question', function (string $field, string $question) {
    install([...installOptions(), "--{$field}" => ''])
        ->expectsQuestion($question, '')
        ->expectsOutputToContain("{$field} field is required")
        ->assertFailed();

    expect(Employee::query()->count())->toBe(0);
})->with([
    ['company', 'Company name'],
    ['currency', 'Company currency (three-letter code, e.g. EUR)'],
    ['department', 'First Department'],
    ['name', 'Your full name'],
    ['email', 'Your email'],
]);

it('confirms where the invitation was sent', function () {
    install(installOptions())
        ->expectsOutputToContain('invitation to set a password has been sent to dana@acme.test')
        ->assertSuccessful();
});

it('asks for any value not given as an option', function () {
    install()
        ->expectsQuestion('Company name', 'Acme Software')
        ->expectsQuestion('Company currency (three-letter code, e.g. EUR)', 'EUR')
        ->expectsQuestion('First Department', 'Operations')
        ->expectsQuestion('Your full name', 'Dana Owner')
        ->expectsQuestion('Your email', 'dana@acme.test')
        ->assertSuccessful();

    expect(Employee::query()->where('email', 'dana@acme.test')->exists())->toBeTrue();
});
