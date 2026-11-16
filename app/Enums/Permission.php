<?php

namespace App\Enums;

/**
 * Everything an Employee can be allowed to do. The list is fixed in code
 * (ADR 0004): companies choose which Permissions go into each Role, but
 * cannot invent new ones. Each value is also the name of its Gate.
 */
enum Permission: string
{
    case ViewEmployeeRecords = 'view-employee-records';
    case ManageEmployees = 'manage-employees';
    case ViewAssignments = 'view-assignments';
    case ManageAssignments = 'manage-assignments';
    case ViewPay = 'view-pay';
    case ManagePay = 'manage-pay';
    case ViewWorkEntries = 'view-work-entries';
    case ManageWorkEntries = 'manage-work-entries';
    case ReopenWorkEntries = 'reopen-work-entries';
    case LockWorkEntries = 'lock-work-entries';
    case ViewTimeOff = 'view-time-off';
    case ManagePaidDays = 'manage-paid-days';
    case ViewDirectory = 'view-directory';
    case ViewProjects = 'view-projects';
    case ManageProjects = 'manage-projects';
    case ManageClients = 'manage-clients';
    case ManageDepartments = 'manage-departments';
    case ManageRoles = 'manage-roles';
    case ManageCompany = 'manage-company';

    /**
     * The Permission's name as people read it, such as "Manage roles".
     */
    public function label(): string
    {
        return ucfirst(str_replace('-', ' ', $this->value));
    }

    /**
     * The area of the HR system the Permission is about, as the Roles page
     * groups them: Employees, Assignments, Pay, Work entries, Time off,
     * Organization or Administration, in the Permissions' own order.
     */
    public function group(): string
    {
        return match ($this) {
            self::ViewEmployeeRecords, self::ManageEmployees => 'Employees',
            self::ViewAssignments, self::ManageAssignments => 'Assignments',
            self::ViewPay, self::ManagePay => 'Pay',
            self::ViewWorkEntries, self::ManageWorkEntries, self::ReopenWorkEntries, self::LockWorkEntries => 'Work entries',
            self::ViewTimeOff, self::ManagePaidDays => 'Time off',
            self::ViewDirectory, self::ViewProjects, self::ManageProjects, self::ManageClients, self::ManageDepartments => 'Organization',
            self::ManageRoles, self::ManageCompany => 'Administration',
        };
    }

    /**
     * Whether this Permission is about Employees and so carries a Scope.
     * The others always apply company-wide.
     */
    public function isScoped(): bool
    {
        return match ($this) {
            self::ViewEmployeeRecords,
            self::ViewAssignments,
            self::ManageAssignments,
            self::ViewPay,
            self::ManagePay,
            self::ViewWorkEntries,
            self::ManageWorkEntries,
            self::ReopenWorkEntries,
            self::ViewTimeOff,
            self::ManagePaidDays => true,
            default => false,
        };
    }

    /**
     * The Scopes a Role can give this Permission with, narrowest first, or
     * none when it applies company-wide. No Employee may reopen their own
     * Work Entries, so reopen can't be given for own.
     *
     * @return list<Scope>
     */
    public function scopes(): array
    {
        if ($this === self::ReopenWorkEntries) {
            return [Scope::Reports, Scope::Everyone];
        }

        return $this->isScoped() ? [Scope::Own, Scope::Reports, Scope::Everyone] : [];
    }
}
