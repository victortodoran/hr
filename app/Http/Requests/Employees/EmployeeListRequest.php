<?php

namespace App\Http\Requests\Employees;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The Employees page's filters: part of a name, a Department, a Manager
 * and an employment status, any of each when none is chosen.
 */
class EmployeeListRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(EmploymentStatus::class)],
            'department' => ['nullable', 'integer', 'min:1'],
            'manager' => ['nullable', 'integer', Rule::exists(Employee::class, 'id')],
        ];
    }

    /**
     * The filters asked for, null where none is set, the part of a name
     * empty when none is.
     *
     * @return array{search: string, status: EmploymentStatus|null, department: int|null, manager: Employee|null}
     */
    public function filters(): array
    {
        return [
            'search' => $this->string('search')->value(),
            'status' => $this->filled('status') ? $this->enum('status', EmploymentStatus::class) : null,
            'department' => $this->filled('department') ? $this->integer('department') : null,
            'manager' => $this->filled('manager') ? Employee::query()->findOrFail($this->integer('manager')) : null,
        ];
    }
}
