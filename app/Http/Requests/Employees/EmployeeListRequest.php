<?php

namespace App\Http\Requests\Employees;

use App\Enums\EmploymentStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The Employees page's filters: part of a name, and an employment status,
 * any status when none is chosen.
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
}
