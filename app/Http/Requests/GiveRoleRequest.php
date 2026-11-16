<?php

namespace App\Http\Requests;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use App\Models\Role;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;

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
            'employee_ids.*' => ['bail', 'integer', 'distinct', $this->canBeGiven()],
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
     * Refuses an Employee who doesn't exist, already holds the Role or has
     * left. The Employees sent and the Role's holders are each loaded once,
     * however many are sent.
     */
    private function canBeGiven(): Closure
    {
        /** @var Collection<int, Employee>|null $sent */
        $sent = null;
        /** @var array<int, int>|null $holding */
        $holding = null;

        return function (string $attribute, mixed $employeeId, Closure $fail) use (&$sent, &$holding): void {
            /** @var Role $role */
            $role = $this->route('role');
            $sent ??= Employee::query()->whereKey(array_filter($this->array('employee_ids'), fn (mixed $id): bool => filter_var($id, FILTER_VALIDATE_INT) !== false))->get()->keyBy('id');
            $holding ??= $role->employees()->pluck('employees.id')->all();
            $employee = $sent->get(intval($employeeId));

            if ($employee === null) {
                $fail(__('validation.exists', ['attribute' => __('Employee')]));
            } elseif (in_array($employee->id, $holding, true)) {
                $fail(__('They already hold this Role.'));
            } elseif ($employee->employmentStatus() === EmploymentStatus::Former) {
                $fail(__('A Former Employee can\'t be given a Role.'));
            }
        };
    }
}
