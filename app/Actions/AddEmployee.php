<?php

namespace App\Actions;

use App\Models\Employee;
use App\Models\Role;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Records a new Employee, puts them on the Internal Project, gives them the
 * Roles marked for new Employees and invites them to set their password.
 */
class AddEmployee
{
    public function __construct(private ChangeAccess $changeAccess) {}

    /**
     * Every Role given is recorded in the Access Log against the actor.
     *
     * @param  array{name: string, email: string, department_id: int, manager_id: int, start_date: Carbon|string, leaving_date?: Carbon|string|null}  $details
     */
    public function add(Employee $actor, array $details): Employee
    {
        $employee = DB::transaction(function () use ($details, $actor): Employee {
            $employee = Employee::query()->create([
                ...$details,
                'password' => Str::random(40), // @pest-mutate-ignore (an unusable placeholder until the invitation is accepted)
            ]);
            $employee->createInternalAssignment();

            Role::query()
                ->where('is_given_to_new_employees', true)
                ->get()
                ->each(fn (Role $role) => $this->changeAccess->giveRole($actor, $role, $employee));

            return $employee;
        });

        $employee->sendInvitation();

        return $employee;
    }
}
