<?php

namespace App\Actions\Company;

use App\Actions\Access\ChangeAccess;
use App\Enums\Permission;
use App\Enums\Scope;
use App\Models\CompanySettings;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InstallCompany
{
    public function __construct(private ChangeAccess $changeAccess) {}

    public function isInstalled(): bool
    {
        return CompanySettings::query()->exists() || Employee::query()->exists();
    }

    public function install(string $company, string $currency, string $department, string $name, string $email): Employee
    {
        $employee = DB::transaction(function () use ($company, $currency, $department, $name, $email): Employee {
            CompanySettings::query()->create(['name' => $company, 'currency' => $currency]);

            $internalProject = new Project(['name' => $company]);
            $internalProject->is_internal = true;
            $internalProject->save();

            $employee = Employee::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => Str::random(40), // @pest-mutate-ignore (an unusable placeholder until the invitation is accepted)
                'department_id' => Department::query()->create(['name' => $department])->id,
                'start_date' => today(),
            ]);
            $employee->createInternalAssignment();

            $admin = $this->changeAccess->createAdminRole($employee, 'Admin');
            $this->createStarterRoles($employee);
            $this->changeAccess->giveRole($employee, $admin, $employee);

            return $employee;
        });

        $employee->sendInvitation();

        return $employee;
    }

    private function createStarterRoles(Employee $installer): void
    {
        $starters = [
            'Manager' => ['grants' => [
                Permission::ViewEmployeeRecords->value => Scope::Reports->value,
                Permission::ViewAssignments->value => Scope::Reports->value,
                Permission::ManageAssignments->value => Scope::Reports->value,
                Permission::ViewPay->value => Scope::Reports->value,
                Permission::ViewWorkEntries->value => Scope::Reports->value,
                Permission::ReopenWorkEntries->value => Scope::Reports->value,
                Permission::ViewTimeOff->value => Scope::Reports->value,
                Permission::ViewTravel->value => Scope::Reports->value,
                Permission::ReopenTravelExpenses->value => Scope::Reports->value,
                Permission::ViewPerformanceEvaluations->value => Scope::Reports->value,
                Permission::ViewProjects->value => null,
            ], 'givenToNewEmployees' => false],
            'Employee' => ['grants' => [
                Permission::ViewDirectory->value => null,
                Permission::ViewEmployeeRecords->value => Scope::Own->value,
                Permission::ViewAssignments->value => Scope::Own->value,
                Permission::ViewPay->value => Scope::Own->value,
                Permission::ViewWorkEntries->value => Scope::Own->value,
                Permission::ManageWorkEntries->value => Scope::Own->value,
                Permission::ViewTimeOff->value => Scope::Own->value,
                Permission::ViewTravel->value => Scope::Own->value,
                Permission::ViewPerformanceEvaluations->value => Scope::Own->value,
            ], 'givenToNewEmployees' => true],
        ];

        foreach ($starters as $name => $starter) {
            $this->changeAccess->createRole($installer, $name, $starter['grants'], $starter['givenToNewEmployees']);
        }
    }
}
