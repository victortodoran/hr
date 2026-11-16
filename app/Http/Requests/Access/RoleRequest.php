<?php

namespace App\Http\Requests\Access;

use App\Enums\EmploymentStatus;
use App\Enums\Permission;
use App\Enums\Scope;
use App\Models\Employee;
use App\Models\Role;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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
            'holders.*' => ['bail', 'integer', 'distinct', $this->stillHereUnlessHolding($role instanceof Role ? $role : null)],
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
     * Refuses a holder who doesn't exist, or who has left, unless they
     * already hold the Role and are only kept on. The holders sent and the
     * Role's current ones are each loaded once, however many are sent.
     */
    private function stillHereUnlessHolding(?Role $role): Closure
    {
        /** @var EloquentCollection<int, Employee>|null $sent */
        $sent = null;
        /** @var array<int, int>|null $holding */
        $holding = null;

        return function (string $attribute, mixed $employeeId, Closure $fail) use ($role, &$sent, &$holding): void {
            $sent ??= Employee::query()->whereKey(array_filter($this->array('holders'), fn (mixed $id): bool => filter_var($id, FILTER_VALIDATE_INT) !== false))->get()->keyBy('id');
            $holding ??= $role?->employees()->pluck('employees.id')->all() ?? [];
            $employee = $sent->get(intval($employeeId));

            if ($employee === null) {
                $fail(__('validation.exists', ['attribute' => __('Employee')]));
            } elseif ($employee->employmentStatus() === EmploymentStatus::Former && ! in_array($employee->id, $holding, true)) {
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
