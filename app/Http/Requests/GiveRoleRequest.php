<?php

namespace App\Http\Requests;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use App\Models\Role;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The Employees to give a Role to, at least one: each someone who hasn't
 * left and doesn't hold the Role yet.
 */
class GiveRoleRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_ids' => ['required', 'array'],
            'employee_ids.*' => ['bail', 'integer', 'distinct', Rule::exists(Employee::class, 'id'), $this->canBeGiven()],
        ];
    }

    /**
     * The Employees being given the Role.
     *
     * @return Collection<int, Employee>
     */
    public function employees(): Collection
    {
        return Employee::query()->whereKey($this->array('employee_ids'))->orderBy('name')->get();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['employee_ids.*' => __('Employee')];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['employee_ids.required' => __('Choose someone to give the Role to.')];
    }

    /**
     * Refuses an Employee who already holds the Role or has left.
     */
    private function canBeGiven(): Closure
    {
        return function (string $attribute, mixed $employeeId, Closure $fail): void {
            /** @var Role $role */
            $role = $this->route('role');
            $employee = Employee::query()->findOrFail(intval($employeeId));

            if ($role->employees()->whereKey($employee->id)->exists()) {
                $fail(__('They already hold this Role.'));
            } elseif ($employee->employmentStatus() === EmploymentStatus::Former) {
                $fail(__('A Former Employee can\'t be given a Role.'));
            }
        };
    }
}
