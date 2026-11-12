<?php

namespace App\Actions;

use App\Enums\Permission;
use App\Enums\Scope;
use App\Models\CompanySettings;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sets up a brand-new installation: the company, its Internal Project, its
 * first Department, the Admin Role and the other starter Roles, and the
 * first Employee as the top Employee with Admin.
 */
class InstallCompany
{
    public function __construct(private ChangeAccess $changeAccess) {}

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

            $internalProject = new Project(['name' => $company]);
            $internalProject->is_internal = true;
            $internalProject->save();

            $employee = new Employee([
                'name' => $name,
                'email' => $email,
                'password' => Str::random(40), // @pest-mutate-ignore (an unusable placeholder until the invitation is accepted)
                'department_id' => Department::query()->create(['name' => $department])->id,
                'start_date' => today(),
            ]);
            $employee->is_top = true;
            $employee->save();
            $employee->createInternalAssignment();

            $admin = $this->changeAccess->createAdminRole($employee, 'Admin');
            $this->createStarterRoles($employee);
            $this->changeAccess->giveRole($employee, $admin, $employee);

            return $employee;
        });

        $employee->sendInvitation();

        return $employee;
    }

    /**
     * Create Manager and Employee as ordinary, editable Roles, logged as
     * created by the first Employee, the one installing the app.
     */
    private function createStarterRoles(Employee $installer): void
    {
        $starters = [
            'Manager' => ['grants' => [
                Permission::ViewEmployeeRecords->value => Scope::Reports->value,
                Permission::ViewAssignments->value => Scope::Reports->value,
                Permission::ManageAssignments->value => Scope::Reports->value,
                Permission::ViewWorkEntries->value => Scope::Reports->value,
                Permission::ViewProjects->value => null,
            ], 'givenToNewEmployees' => false],
            'Employee' => ['grants' => [
                Permission::ViewDirectory->value => null,
                Permission::ViewEmployeeRecords->value => Scope::Own->value,
                Permission::ViewAssignments->value => Scope::Own->value,
                Permission::ViewPay->value => Scope::Own->value,
                Permission::ManageWorkEntries->value => Scope::Own->value,
            ], 'givenToNewEmployees' => true],
        ];

        foreach ($starters as $name => $starter) {
            $this->changeAccess->createRole($installer, $name, $starter['grants'], $starter['givenToNewEmployees']);
        }
    }
}
