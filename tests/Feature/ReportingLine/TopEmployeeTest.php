<?php

use App\Actions\NameTopEmployee;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\TopEmployeeController;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\WorkEntry;
use App\Policies\EmployeePolicy;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

covers(TopEmployeeController::class, EmployeeController::class, NameTopEmployee::class, EmployeePolicy::class);

it('hands the top spot to another Employee, and the old top then reports to them', function () {
    $dana = Employee::factory()->top()->create();
    $ben = Employee::factory()->create(['manager_id' => $dana->id]);

    actingAs(employeeWhoManagesTheCompany())
        ->post(route('employees.top.store', $ben))
        ->assertRedirect(route('employees.edit', $ben))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => "{$ben->name} is now the top Employee."]);

    expect($ben->refresh()->is_top)->toBeTrue()
        ->and($ben->manager_id)->toBeNull()
        ->and($dana->refresh()->is_top)->toBeFalse()
        ->and($dana->manager_id)->toBe($ben->id)
        ->and(Employee::query()->where('is_top', true)->pluck('id')->all())->toBe([$ben->id]);
});

it('lets the old top Employee be given a Leaving Date once they have handed over', function () {
    $dana = Employee::factory()->top()->create();
    $ben = Employee::factory()->create(['manager_id' => $dana->id]);
    $admin = employeeWhoManagesTheCompany();

    actingAs($admin)->post(route('employees.top.store', $ben));

    $dana->refresh();

    actingAs($admin)
        ->patch(route('employees.update', $dana), [
            'name' => $dana->name,
            'email' => $dana->email,
            'department_id' => $dana->department_id,
            'manager_id' => $ben->id,
            'start_date' => $dana->start_date->toDateString(),
            'leaving_date' => today()->addMonth()->toDateString(),
        ])
        ->assertSessionHasNoErrors();
});

it('only lets a current Employee who is not already top, and is not leaving, take the top spot', function (string $state) {
    $dana = Employee::factory()->top()->create();
    $candidate = match ($state) {
        'top' => $dana,
        'leaving' => Employee::factory()->create(['manager_id' => $dana->id, 'leaving_date' => today()->addMonth()->toDateString()]),
        default => Employee::factory()->{$state}()->create(['manager_id' => $dana->id]),
    };

    actingAs(employeeWhoManagesTheCompany())
        ->post(route('employees.top.store', $candidate))
        ->assertForbidden();

    expect(Employee::query()->where('is_top', true)->pluck('id')->all())->toBe([$dana->id]);
})->with(['future', 'former', 'top', 'leaving']);

it('refuses naming the top Employee without manage company', function () {
    $dana = Employee::factory()->top()->create();
    $ben = Employee::factory()->create(['manager_id' => $dana->id]);

    actingAs(employeeWhoManagesEmployees())
        ->post(route('employees.top.store', $ben))
        ->assertForbidden();

    expect($dana->refresh()->is_top)->toBeTrue();
});

it('offers handing over the top spot only to those allowed, for Employees who can take it', function () {
    $dana = Employee::factory()->top()->create();
    $ben = Employee::factory()->create(['manager_id' => $dana->id]);

    actingAs(employeeWhoManagesTheCompany())->get(route('employees.edit', $ben))
        ->assertInertia(fn (Assert $page) => $page->where('employee.can_become_top', true));

    actingAs(employeeWhoManagesTheCompany())->get(route('employees.edit', $dana))
        ->assertInertia(fn (Assert $page) => $page->where('employee.can_become_top', false));

    actingAs(employeeWhoManagesEmployees())->get(route('employees.edit', $ben))
        ->assertInertia(fn (Assert $page) => $page->where('employee.can_become_top', false));
});

it('refuses to make someone the top Employee while they have Pending Work Entries, which nobody could then approve', function () {
    $dana = Employee::factory()->top()->create();
    $ben = Employee::factory()->create(['name' => 'Ben', 'manager_id' => $dana->id]);
    WorkEntry::factory()->for(Assignment::factory()->for($ben))->create();

    actingAs(employeeWhoManagesTheCompany())
        ->post(route('employees.top.store', $ben))
        ->assertSessionHasErrors(['employee' => 'Ben has Pending Work Entries, which nobody could approve once Ben is the top Employee. Have their Manager approve them first, or delete them.']);

    expect($ben->refresh()->is_top)->toBeFalse()
        ->and($dana->refresh()->is_top)->toBeTrue();
});

it('makes someone the top Employee when their Work Entries are all Approved or Locked', function () {
    $dana = Employee::factory()->top()->create();
    $ben = Employee::factory()->create(['manager_id' => $dana->id]);
    $assignment = Assignment::factory()->for($ben)->create();
    WorkEntry::factory()->for($assignment)->approved()->create();
    WorkEntry::factory()->for($assignment)->locked()->create();

    actingAs(employeeWhoManagesTheCompany())
        ->post(route('employees.top.store', $ben))
        ->assertSessionHasNoErrors();

    expect($ben->refresh()->is_top)->toBeTrue();
});
