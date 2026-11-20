<?php

namespace App\Actions\Employees;

use App\Actions\Access\ChangeAccess;
use App\Models\Employee;
use App\Models\Role;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AddEmployee
{
    public function __construct(private ChangeAccess $changeAccess) {}

    /**
     * The actor reads their Reports afresh, as the new Employee may be among
     * them.
     *
     * @param  array{name: string, email: string, department_id: int, manager_id: int|null, start_date: Carbon|string, leaving_date?: Carbon|string|null, job_title: string}  $details
     */
    public function add(Employee $actor, array $details): Employee
    {
        $employee = DB::transaction(function () use ($details, $actor): Employee {
            $employee = Employee::query()->create([
                ...Arr::except($details, 'job_title'),
                'password' => Str::random(40), // @pest-mutate-ignore (an unusable placeholder until the invitation is accepted)
            ]);
            $employee->createInternalAssignment($details['job_title']);

            Role::query()
                ->where('is_given_to_new_employees', true)
                ->get()
                ->each(fn (Role $role) => $this->changeAccess->giveRole($actor, $role, $employee));

            return $employee;
        });

        $actor->forgetAccess();
        $employee->sendInvitation();

        return $employee;
    }
}
