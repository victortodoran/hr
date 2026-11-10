<?php

namespace App\Http\Requests;

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An Employee's record, when adding or editing them.
 */
class EmployeeRequest extends FormRequest
{
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
                : ['required', 'integer', Rule::exists(Employee::class, 'id'), Rule::notIn([$employee?->id])],
            'start_date' => ['required', 'date'],
        ];
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
        ];
    }
}
