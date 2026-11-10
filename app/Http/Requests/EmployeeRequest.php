<?php

namespace App\Http\Requests;

use App\Enums\EmploymentStatus;
use App\Models\Department;
use App\Models\Employee;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * An Employee's record, when adding or editing them.
 */
class EmployeeRequest extends FormRequest
{
    /**
     * Emails are compared and stored in lowercase, so capitals never make
     * two addresses different.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower($this->input('email'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $employee = $this->route('employee');
        $employee = $employee instanceof Employee ? $employee : null; // @pest-mutate-ignore (the route parameter is always an Employee or absent)

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(Employee::class)->ignore($employee?->id)],
            'department_id' => ['required', 'integer', Rule::exists(Department::class, 'id')],
            'manager_id' => $employee?->is_top
                ? ['prohibited']
                : ['bail', 'required', 'integer', Rule::exists(Employee::class, 'id'), Rule::notIn([$employee?->id]), $this->noLoopUnder($employee)],
            'start_date' => ['required', 'date'],
            'leaving_date' => match (true) {
                $employee?->is_top === true => ['prohibited'], // @pest-mutate-ignore (adding has no Employee yet, and PHP reads a property of null as null)
                $employee?->employmentStatus() === EmploymentStatus::Former => [Rule::in([$employee->leaving_date?->toDateString()])], // @pest-mutate-ignore (a Former Employee always has a Leaving Date)
                default => ['nullable', 'date', 'after_or_equal:start_date', $this->keepsSomeoneWhoCanManageRoles($employee)],
            },
        ];
    }

    /**
     * Refuses a new Manager who reports to the Employee, directly or not,
     * since that would turn the reporting line into a loop.
     */
    private function noLoopUnder(?Employee $employee): Closure
    {
        return function (string $attribute, mixed $managerId, Closure $fail) use ($employee): void {
            if ($employee !== null && $employee->hasInReports(Employee::query()->whereKey($managerId)->firstOrFail())) {
                $fail(__('That would make a loop: the new Manager reports to this Employee.'));
            }
        };
    }

    /**
     * Refuses a Leaving Date for the last Employee who can manage Roles,
     * since the company would lose the ability to change access.
     */
    private function keepsSomeoneWhoCanManageRoles(?Employee $employee): Closure
    {
        return function (string $attribute, mixed $leavingDate, Closure $fail) use ($employee): void {
            $lastingRoleManagers = Employee::query()->lastingRoleManagers();

            if ($employee !== null
                && (clone $lastingRoleManagers)->whereKey($employee->id)->exists()
                && ! $lastingRoleManagers->whereKeyNot($employee->id)->exists()) {
                $fail(__(':name is the last Employee who can manage Roles. Give manage roles to another current Employee before setting a Leaving Date.', ['name' => $employee->name]));
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'department_id' => __('Department'),
            'manager_id' => __('Manager'),
            'start_date' => __('Start Date'),
            'leaving_date' => __('Leaving Date'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'manager_id.required' => __('Choose a Manager. Only the top Employee has none.'),
            'manager_id.prohibited' => __('The top Employee has no Manager.'),
            'manager_id.not_in' => __('An Employee cannot be their own Manager.'),
            'leaving_date.prohibited' => __('Name a new top Employee before the current one can leave.'),
            'leaving_date.in' => __('A Leaving Date that has passed can\'t be changed.'),
            'leaving_date.after_or_equal' => __('The Leaving Date must be on or after the Start Date.'),
        ];
    }
}
