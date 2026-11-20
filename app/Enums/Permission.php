<?php

namespace App\Enums;

/** The list is fixed in code (ADR 0004), and each value is also the name of its Gate. */
enum Permission: string
{
    case ViewEmployeeRecords = 'view-employee-records';
    case AddEmployees = 'add-employees';
    case ManageEmployees = 'manage-employees';
    case ViewAssignments = 'view-assignments';
    case ManageAssignments = 'manage-assignments';
    case ViewPay = 'view-pay';
    case ManagePay = 'manage-pay';
    case ViewWorkEntries = 'view-work-entries';
    case ManageWorkEntries = 'manage-work-entries';
    case ReopenWorkEntries = 'reopen-work-entries';
    case ViewTimeOff = 'view-time-off';
    case ManagePaidDays = 'manage-paid-days';
    case ViewTravel = 'view-travel';
    case ReopenTravelExpenses = 'reopen-travel-expenses';
    case ViewPerformanceEvaluations = 'view-performance-evaluations';
    case ReopenPerformanceEvaluations = 'reopen-performance-evaluations';
    case ViewDirectory = 'view-directory';
    case ViewProjects = 'view-projects';
    case ManageProjects = 'manage-projects';
    case ManageClients = 'manage-clients';
    case ManageDepartments = 'manage-departments';
    case ManageRoles = 'manage-roles';
    case ManageCompany = 'manage-company';

    /** Domain terms in Title Case, everything else in sentence case (rules C1 and C2). */
    public function label(): string
    {
        return match ($this) {
            self::ViewEmployeeRecords => 'View Employee records',
            self::AddEmployees => 'Add Employees',
            self::ManageEmployees => 'Manage Employees',
            self::ViewAssignments => 'View Assignments',
            self::ManageAssignments => 'Manage Assignments',
            self::ViewPay => 'View Pay',
            self::ManagePay => 'Manage Pay',
            self::ViewWorkEntries => 'View Work Entries',
            self::ManageWorkEntries => 'Manage Work Entries',
            self::ReopenWorkEntries => 'Reopen Work Entries',
            self::ViewTimeOff => 'View Time Off',
            self::ManagePaidDays => 'Manage Paid Days',
            self::ViewTravel => 'View travel',
            self::ReopenTravelExpenses => 'Reopen Travel Expenses',
            self::ViewPerformanceEvaluations => 'View Performance Evaluations',
            self::ReopenPerformanceEvaluations => 'Reopen Performance Evaluations',
            self::ViewDirectory => 'View directory',
            self::ViewProjects => 'View Projects',
            self::ManageProjects => 'Manage Projects',
            self::ManageClients => 'Manage Clients',
            self::ManageDepartments => 'Manage Departments',
            self::ManageRoles => 'Manage Roles',
            self::ManageCompany => 'Manage company',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::ViewEmployeeRecords, self::AddEmployees, self::ManageEmployees => 'Employees',
            self::ViewAssignments, self::ManageAssignments => 'Assignments',
            self::ViewPay, self::ManagePay => 'Pay',
            self::ViewWorkEntries, self::ManageWorkEntries, self::ReopenWorkEntries => 'Work Entries',
            self::ViewTimeOff, self::ManagePaidDays => 'Time Off',
            self::ViewTravel, self::ReopenTravelExpenses => 'Travel',
            self::ViewPerformanceEvaluations, self::ReopenPerformanceEvaluations => 'Performance',
            self::ViewDirectory, self::ViewProjects, self::ManageProjects, self::ManageClients, self::ManageDepartments => 'Organization',
            self::ManageRoles, self::ManageCompany => 'Administration',
        };
    }

    /** Add employees applies company-wide, as nobody reports to an Employee who isn't there yet. */
    public function isScoped(): bool
    {
        return match ($this) {
            self::ViewEmployeeRecords,
            self::ManageEmployees,
            self::ViewAssignments,
            self::ManageAssignments,
            self::ViewPay,
            self::ManagePay,
            self::ViewWorkEntries,
            self::ManageWorkEntries,
            self::ReopenWorkEntries,
            self::ViewTimeOff,
            self::ManagePaidDays,
            self::ViewTravel,
            self::ReopenTravelExpenses,
            self::ViewPerformanceEvaluations,
            self::ReopenPerformanceEvaluations => true,
            default => false,
        };
    }

    /**
     * No Employee may reopen their own Work Entries, Travel Expenses or
     * Performance Evaluations, so reopen can't be given for own.
     *
     * @return list<Scope>
     */
    public function scopes(): array
    {
        if (in_array($this, [self::ReopenWorkEntries, self::ReopenTravelExpenses, self::ReopenPerformanceEvaluations], true)) {
            return [Scope::Reports, Scope::Everyone];
        }

        return $this->isScoped() ? [Scope::Own, Scope::Reports, Scope::Everyone] : [];
    }

    /**
     * Pay belongs to the Assignment (ADR 0002), so seeing it shows them; the one
     * list the policy, the lists and the flags use.
     *
     * @return list<Permission>
     */
    public static function seeingAssignments(): array
    {
        return [self::ViewAssignments, self::ManageAssignments, self::ViewPay, self::ManagePay];
    }

    /**
     * The one list the policy and the command menu's search use.
     *
     * @return list<Permission>
     */
    public static function seeingEmployeePages(): array
    {
        return [self::ViewEmployeeRecords, ...self::seeingAssignments(), ...self::seeingTimeOff()];
    }

    /**
     * You can always see what you can act on; the one list the policy, the lists
     * and the flags use.
     *
     * @return list<Permission>
     */
    public static function seeingWorkEntries(): array
    {
        return [self::ViewWorkEntries, self::ManageWorkEntries, self::ReopenWorkEntries];
    }

    /**
     * You can always see what you can act on; the one list the policies, the
     * Travel Requests page and the flags use.
     *
     * @return list<Permission>
     */
    public static function seeingTravel(): array
    {
        return [self::ViewTravel, self::ReopenTravelExpenses];
    }

    /**
     * Setting Paid Days means seeing the Time Off that uses them; the one list
     * the policy, the Time Off page and the flags use.
     *
     * @return list<Permission>
     */
    public static function seeingTimeOff(): array
    {
        return [self::ViewTimeOff, self::ManagePaidDays];
    }

    /**
     * You can always see what you can act on; the one list the policy, the
     * Performance Evaluations page and the flags use.
     *
     * @return list<Permission>
     */
    public static function seeingPerformanceEvaluations(): array
    {
        return [self::ViewPerformanceEvaluations, self::ReopenPerformanceEvaluations];
    }
}
