<?php

namespace App\Actions;

use App\Enums\Permission;
use App\Enums\Scope;
use App\Models\CompanySettings;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sets up a brand-new installation: the company, its first Department, the
 * starter Roles, and the first Employee as the top Employee with Admin.
 */
class InstallCompany
{
    /**
     * Whether the app has already been installed.
     */
    public function isInstalled(): bool
    {
        return CompanySettings::query()->exists() || Employee::query()->exists();
    }

    /**
     * Install the company and return its first Employee, who has been sent
     * an invitation to set their password.
     */
    public function install(string $company, string $currency, string $department, string $name, string $email): Employee
    {
        $employee = DB::transaction(function () use ($company, $currency, $department, $name, $email): Employee {
            CompanySettings::query()->create(['name' => $company, 'currency' => $currency]);

            $roles = $this->createStarterRoles();

            $employee = new Employee([
                'name' => $name,
                'email' => $email,
                'password' => Str::random(40), // @pest-mutate-ignore (an unusable placeholder until the invitation is accepted)
                'department_id' => Department::query()->create(['name' => $department])->id,
            ]);
            $employee->is_top = true;
            $employee->save();

            $employee->roles()->attach($roles['Admin']);

            return $employee;
        });

        $employee->sendInvitation();

        return $employee;
    }

    /**
     * Create Admin, Manager and Employee as ordinary, editable Roles.
     *
     * @return array<string, Role>
     */
    private function createStarterRoles(): array
    {
        $admin = [];
        foreach (Permission::cases() as $permission) {
            $admin[$permission->value] = $permission->isScoped() ? Scope::Everyone : null;
        }

        $starters = [
            'Admin' => $admin,
            'Manager' => [
                Permission::ViewEmployeeRecords->value => Scope::Reports,
                Permission::ViewAssignments->value => Scope::Reports,
                Permission::ManageAssignments->value => Scope::Reports,
                Permission::ViewProjects->value => null,
            ],
            'Employee' => [
                Permission::ViewDirectory->value => null,
                Permission::ViewEmployeeRecords->value => Scope::Own,
                Permission::ViewAssignments->value => Scope::Own,
                Permission::ViewPay->value => Scope::Own,
            ],
        ];

        $roles = [];
        foreach ($starters as $name => $grants) {
            $role = Role::query()->create(['name' => $name]);

            foreach ($grants as $permission => $scope) {
                $role->permissions()->create(['permission' => $permission, 'scope' => $scope]);
            }

            $roles[$name] = $role;
        }

        return $roles;
    }
}
