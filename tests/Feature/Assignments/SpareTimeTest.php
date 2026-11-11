<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\SpareTimeController;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\Project;
use App\Policies\AssignmentPolicy;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\travelTo;

covers(SpareTimeController::class, AssignmentPolicy::class, Employee::class, Assignment::class);

beforeEach(fn () => travelTo('2026-03-01 09:00'));

/**
 * An Employee with their Internal Assignment and, optionally, a share on a
 * Project from a given day.
 *
 * @param  array<string, mixed>  $attributes
 * @param  array<string, mixed>  $internal
 */
function bookedEmployee(array $attributes, ?int $projectShare = null, string $projectFrom = '2026-01-01', array $internal = []): Employee
{
    $employee = Employee::factory()->create(['start_date' => '2024-01-01', ...$attributes]);
    Assignment::factory()->internal()->for($employee)->create($internal);

    if ($projectShare !== null) {
        Assignment::factory()->for($employee)->for(Project::factory()->create(['start_date' => '2024-01-01']))
            ->create(['start_date' => $projectFrom, 'share' => $projectShare]);
    }

    return $employee;
}

it('lists who has internal time above 0% on a chosen day, by name, with how much', function () {
    $viewer = employeeWith([Permission::ViewAssignments->value => Scope::Everyone], ['name' => 'Zed Viewer', 'start_date' => '2024-01-01']);
    $ana = bookedEmployee(['name' => 'Ana'], projectShare: 60);
    $ben = bookedEmployee(['name' => 'Ben'], internal: ['job_title' => 'Designer', 'share' => 40], projectShare: 80);
    bookedEmployee(['name' => 'Cara'], projectShare: 100);
    $dev = bookedEmployee(['name' => 'Dev'], projectShare: 100, projectFrom: '2026-04-01');
    bookedEmployee(['name' => 'Eve', 'start_date' => '2026-04-01']);
    bookedEmployee(['name' => 'Finn', 'leaving_date' => '2026-02-28']);
    $gus = bookedEmployee(['name' => 'Gus'], projectShare: 99);

    actingAs($viewer)->get(route('spare-time.index', ['day' => '2026-03-16']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('spare-time/Index')
            ->where('day', '2026-03-16')
            ->where('employees', [
                ['id' => $ana->id, 'name' => 'Ana', 'is_over_booked' => false, 'job_title' => null, 'internal_time' => 40],
                ['id' => $ben->id, 'name' => 'Ben', 'is_over_booked' => true, 'job_title' => 'Designer', 'internal_time' => 40],
                ['id' => $dev->id, 'name' => 'Dev', 'is_over_booked' => false, 'job_title' => null, 'internal_time' => 100],
                ['id' => $gus->id, 'name' => 'Gus', 'is_over_booked' => false, 'job_title' => null, 'internal_time' => 1],
            ]),
        );
});

it('looks at today unless another day is chosen', function (array $query) {
    bookedEmployee(['name' => 'Dev'], projectShare: 100, projectFrom: '2026-03-02');

    actingAs(employeeWith([Permission::ViewAssignments->value => Scope::Everyone]))
        ->get(route('spare-time.index', $query))
        ->assertInertia(fn (Assert $page) => $page
            ->where('day', '2026-03-01')
            ->where('employees', fn (Collection $employees) => $employees->pluck('name')->all() === ['Dev']),
        );
})->with(['no day' => [[]], 'an empty day' => [['day' => '']]]);

it('refuses a day that isn\'t a date', function () {
    actingAs(employeeWith([Permission::ViewAssignments->value => Scope::Everyone]))
        ->get(route('spare-time.index', ['day' => 'soon']))
        ->assertSessionHasErrors(['day' => 'The day field must be a valid date.']);
});

/*
 * Dana (top)
 * ├── Ben ── Cara ── Dev
 * └── Eve
 */
it('lists only the Employees whose Assignments the viewer\'s Scope covers', function (array $grants, array $visible) {
    $dana = bookedEmployee(['name' => 'Dana', 'is_top' => true]);
    $ben = bookedEmployee(['name' => 'Ben', 'manager_id' => $dana->id]);
    giveRole($ben, $grants);
    $cara = bookedEmployee(['name' => 'Cara', 'manager_id' => $ben->id]);
    bookedEmployee(['name' => 'Dev', 'manager_id' => $cara->id]);
    bookedEmployee(['name' => 'Eve', 'manager_id' => $dana->id]);

    actingAs($ben)->get(route('spare-time.index'))
        ->assertInertia(fn (Assert $page) => $page->where('employees', fn (Collection $employees) => $employees->pluck('name')->all() === $visible));
})->with([
    'view, reports' => [[Permission::ViewAssignments->value => Scope::Reports], ['Cara', 'Dev']],
    'manage, everyone' => [[Permission::ManageAssignments->value => Scope::Everyone], ['Ben', 'Cara', 'Dana', 'Dev', 'Eve']],
    'view own, manage reports' => [[Permission::ViewAssignments->value => Scope::Own, Permission::ManageAssignments->value => Scope::Reports], ['Ben', 'Cara', 'Dev']],
]);

it('refuses the spare-time view to an Employee whose Assignments Scope covers only themselves, or nobody', function (array $grants) {
    actingAs(employeeWith($grants))->get(route('spare-time.index'))->assertForbidden();
})->with([
    'view own' => [[Permission::ViewAssignments->value => Scope::Own]],
    'manage own' => [[Permission::ManageAssignments->value => Scope::Own]],
    'neither' => [[Permission::ViewDirectory->value => null]],
]);

it('sends a guest to sign in instead of showing spare time', function () {
    get(route('spare-time.index'))->assertRedirect(route('login'));
});

it('tells the pages whether the signed-in Employee may see spare time', function (array $grants, bool $expected) {
    actingAs(employeeWith($grants))->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canSeeSpareTime', $expected));
})->with([
    'view reports' => [[Permission::ViewAssignments->value => Scope::Reports], true],
    'manage everyone' => [[Permission::ManageAssignments->value => Scope::Everyone], true],
    'view own' => [[Permission::ViewAssignments->value => Scope::Own], false],
]);
