<?php

use App\Http\Requests\Auth\AcceptInvitationRequest;
use App\Http\Requests\Employees\EmployeeRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Project;
use App\Notifications\Invitation;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\post;

covers(Employee::class, EmployeeRequest::class, AcceptInvitationRequest::class);

beforeEach(function () {
    Notification::fake();
    Project::factory()->internal()->create();
});

/**
 * @return array<string, mixed>
 */
function detailsWithEmail(string $email): array
{
    return [
        'name' => 'Cara Lane',
        'email' => $email,
        'department_id' => Department::factory()->create()->id,
        'manager_id' => Employee::factory()->create()->id,
        'start_date' => '2025-01-06',
    ];
}

it('stores an Employee\'s email in lowercase, however HR types it', function () {
    actingAs(employeeWhoManagesEmployees())
        ->post(route('employees.store'), detailsWithEmail('Cara.Lane@ACME.test'))
        ->assertSessionHasNoErrors();

    expect(Employee::query()->where('name', 'Cara Lane')->sole()->email)->toBe('cara.lane@acme.test');

    Notification::assertSentTo(Employee::query()->where('email', 'cara.lane@acme.test')->sole(), Invitation::class);
});

it('treats an email that differs only in capitals as already used', function () {
    Employee::factory()->create(['email' => 'cara@acme.test']);

    actingAs(employeeWhoManagesEmployees())
        ->post(route('employees.store'), detailsWithEmail('CARA@acme.test'))
        ->assertSessionHasErrors(['email' => 'The email has already been taken.']);
});

it('says a Former Employee\'s email can\'t be reused, since a rehire needs a new address', function () {
    Employee::factory()->former()->create(['email' => 'cara@acme.test']);

    actingAs(employeeWhoManagesEmployees())
        ->post(route('employees.store'), detailsWithEmail('cara@acme.test'))
        ->assertSessionHasErrors(['email' => 'This email belongs to a Former Employee. A rehired person is added as a new Employee, so give them a new email.']);
});

it('refuses changing an email to another Employee\'s in different capitals, but accepts the Employee\'s own', function () {
    Employee::factory()->create(['email' => 'ben@acme.test']);
    $cara = Employee::factory()->create(['email' => 'cara@acme.test', 'manager_id' => Employee::factory()->create()->id]);
    $record = [
        'name' => $cara->name,
        'department_id' => $cara->department_id,
        'manager_id' => $cara->manager_id,
        'start_date' => $cara->start_date->toDateString(),
    ];

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), [...$record, 'email' => 'Ben@acme.test'])
        ->assertSessionHasErrors(['email' => 'The email has already been taken.']);

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), [...$record, 'email' => 'CARA@acme.test'])
        ->assertSessionHasNoErrors();

    expect($cara->refresh()->email)->toBe('cara@acme.test');
});

it('lets an Employee sign in whatever capitals they type', function () {
    $cara = Employee::factory()->create(['email' => 'Cara@Acme.test']);

    post(route('login.store'), ['email' => 'CARA@acme.TEST', 'password' => 'password']);

    assertAuthenticatedAs($cara);
});

it('lets an invited Employee set their password whatever capitals the email has', function () {
    $cara = Employee::factory()->invited()->create(['email' => 'cara@acme.test']);
    $token = Password::broker('invites')->createToken($cara);

    post(route('invitation.store'), [
        'token' => $token,
        'email' => 'Cara@Acme.Test',
        'password' => 'a-new-password',
        'password_confirmation' => 'a-new-password',
    ])->assertRedirect(route('login'));

    expect($cara->refresh()->hasPendingInvitation())->toBeFalse();
});
