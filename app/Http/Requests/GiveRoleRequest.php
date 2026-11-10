<?php

namespace App\Http\Requests;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use App\Models\Role;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The Employee to give a Role to: someone who hasn't left and doesn't hold
 * the Role yet.
 */
class GiveRoleRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['bail', 'required', 'integer', Rule::exists(Employee::class, 'id'), $this->canBeGiven()],
        ];
    }

    /**
     * The Employee being given the Role.
     */
    public function employee(): Employee
    {
        return Employee::query()->findOrFail($this->integer('employee_id'));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['employee_id' => __('Employee')];
    }

    /**
     * Refuses an Employee who already holds the Role or has left.
     */
    private function canBeGiven(): Closure
    {
        return function (string $attribute, mixed $employeeId, Closure $fail): void {
            /** @var Role $role */
            $role = $this->route('role');
            $employee = $this->employee();

            if ($role->employees()->whereKey($employee->id)->exists()) {
                $fail(__('They already hold this Role.'));
            } elseif ($employee->employmentStatus() === EmploymentStatus::Former) {
                $fail(__('A Former Employee can\'t be given a Role.'));
            }
        };
    }
}
