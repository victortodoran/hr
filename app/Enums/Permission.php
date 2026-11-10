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
            self::ManagePay => true,
            default => false,
        };
    }
}
