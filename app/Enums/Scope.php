<?php

namespace App\Enums;

use App\Models\Builders\EmployeeBuilder;
use App\Models\Employee;

/**
 * Which Employees a Permission about Employees applies to. The Scopes an
 * Employee's Roles give for one Permission combine: own and reports
 * together cover themselves and their Reports.
 */
enum Scope: string
{
    case Own = 'own';
    case Reports = 'reports';
    case Everyone = 'everyone';

    /**
     * Whether this Scope can cover anyone besides the acting Employee.
     */
    public function coversOthers(): bool
    {
        return $this !== self::Own;
    }

    /**
     * Whether this Scope, held by the acting Employee, covers the target.
     * Reports are everyone below the acting Employee in the reporting line,
     * never the acting Employee themselves.
     */
    public function covers(Employee $actor, Employee $target): bool
    {
        return match ($this) {
            self::Own => $actor->is($target),
            self::Reports => ! $actor->is($target) && $actor->hasInReports($target),
            self::Everyone => true,
        };
    }

    /**
     * Limit a query of Employees to those this Scope, held by the acting
     * Employee, covers: the query counterpart of covers(). Each Scope adds
     * a condition, even everyone, so Scopes can be combined with orWhere.
     */
    public function limit(EmployeeBuilder $employees, Employee $actor): void
    {
        match ($this) {
            self::Own => $employees->whereKey($actor->id),
            self::Reports => $employees->reportsOf($actor),
            self::Everyone => $employees->whereRaw('true'),
        };
    }
}
