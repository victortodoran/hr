<?php

namespace App\Enums;

use App\Models\Builders\EmployeeBuilder;
use App\Models\Employee;

enum Scope: string
{
    case Own = 'own';
    case Reports = 'reports';
    case Everyone = 'everyone';

    public function coversOthers(): bool
    {
        return $this !== self::Own;
    }

    /** Reports never include the acting Employee themselves. */
    public function covers(Employee $actor, Employee $target): bool
    {
        return match ($this) {
            self::Own => $actor->is($target),
            self::Reports => ! $actor->is($target) && $actor->hasInReports($target),
            self::Everyone => true,
        };
    }

    /** Each Scope adds a condition, even everyone, so Scopes can be combined with orWhere. */
    public function limit(EmployeeBuilder $employees, Employee $actor): void
    {
        match ($this) {
            self::Own => $employees->whereKey($actor->id),
            self::Reports => $employees->reportsOf($actor),
            self::Everyone => $employees->whereRaw('true'),
        };
    }
}
