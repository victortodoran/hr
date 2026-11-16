<?php

namespace App\Http\Requests;

use App\Enums\EmploymentStatus;
use App\Enums\Permission;
use App\Enums\Scope;
use App\Models\Employee;
use App\Models\Role;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A Role's name, Permissions and whether new Employees get it, when creating
 * or editing one, and, when sent, everyone who should hold it. Permissions
 * arrive as a map from each chosen Permission to its Scope, or to null for
 * company-wide Permissions. A holder must not have left, unless they already
 * hold the Role.
 */
class RoleRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $role = $this->route('role');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Role::class)->ignore($role instanceof Role ? $role->id : null),
            ],
            'permissions' => ['present', 'array', $this->eachPermissionWithItsScope()],
            'given_to_new_employees' => ['required', 'boolean'],
            'holders' => ['sometimes', 'array'],
            'holders.*' => ['bail', 'integer', 'distinct', Rule::exists(Employee::class, 'id'), $this->stillHereUnlessHolding($role instanceof Role ? $role : null)],
        ];
    }

    /**
     * The chosen Permissions, each with its Scope or null.
     *
     * @return array<string, string|null>
     */
    public function grants(): array
    {
        /** @var array<string, string|null> $permissions */
        $permissions = $this->validated('permissions');

        return $permissions;
    }

    /**
     * The IDs of everyone who should hold the Role, or null when none were
     * sent, to leave its holders as they are.
     *
     * @return array<int, int>|null
     */
    public function holders(): ?array
    {
        return $this->has('holders') ? array_map(intval(...), $this->array('holders')) : null;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['holders.*' => __('Employee')];
    }

    /**
     * Whether every new Employee should be given the Role.
     */
    public function givenToNewEmployees(): bool
    {
        return $this->boolean('given_to_new_employees');
    }

    /**
     * Refuses a Former Employee as a holder, unless they already hold the
     * Role and are only kept on.
     */
    private function stillHereUnlessHolding(?Role $role): Closure
    {
        return function (string $attribute, mixed $employeeId, Closure $fail) use ($role): void {
            $employee = Employee::query()->findOrFail(intval($employeeId));

            if ($employee->employmentStatus() === EmploymentStatus::Former
                && ! ($role?->employees()->whereKey($employee->id)->exists() ?? false)) {
                $fail(__(':name has left, so they can\'t be given a Role.', ['name' => $employee->name]));
            }
        };
    }

    /**
     * Accepts only Permissions the HR system defines, where each one about
     * Employees has one of the Scopes it can carry and every other one has
     * none.
     */
    private function eachPermissionWithItsScope(): Closure
    {
        return function (string $attribute, mixed $permissions, Closure $fail): void {
            foreach ((array) $permissions as $name => $scope) {
                $permission = Permission::tryFrom($name);

                if ($permission === null) {
                    $fail(__('The HR system has no Permission called ":name".', ['name' => $name]));
                } elseif ($permission->isScoped() && ! in_array($scope, array_column($permission->scopes(), 'value'), true)) {
                    $fail(__('Choose a Scope for :permission: :scopes.', [
                        'permission' => $permission->label(),
                        'scopes' => collect($permission->scopes())->map(fn (Scope $choice): string => $choice->value)->join(', ', ' or '),
                    ]));
                } elseif (! $permission->isScoped() && $scope !== null) {
                    $fail(__(':permission always applies company-wide, so it takes no Scope.', ['permission' => $permission->label()]));
                }
            }
        };
    }
}
