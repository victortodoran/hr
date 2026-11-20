<?php

namespace App\Http\Requests\Employees;

use App\Enums\EmploymentStatus;
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
            'manager' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * The part of a name asked for, empty when none is.
     */
    public function search(): string
    {
        return $this->string('search')->value();
    }

    /**
     * The employment status asked for, null when none is.
     */
    public function status(): ?EmploymentStatus
    {
        return $this->filled('status') ? $this->enum('status', EmploymentStatus::class) : null;
    }

    /**
     * The ID of the Department asked for, null when none is.
     */
    public function departmentId(): ?int
    {
        return $this->filled('department') ? $this->integer('department') : null;
    }

    /**
     * The ID of the effective Manager asked for, null when none is.
     */
    public function managerId(): ?int
    {
        return $this->filled('manager') ? $this->integer('manager') : null;
    }
}
