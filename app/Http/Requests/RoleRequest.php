<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use App\Enums\Scope;
use App\Models\Role;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A Role's name, Permissions and whether new Employees get it, when creating
 * or editing one. Permissions arrive as a map from each chosen Permission to
 * its Scope, or to null for company-wide Permissions.
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
     * Whether every new Employee should be given the Role.
     */
    public function givenToNewEmployees(): bool
    {
        return $this->boolean('given_to_new_employees');
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
