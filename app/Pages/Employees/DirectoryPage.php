<?php

namespace App\Pages\Employees;

use App\Models\Employee;
use App\Support\ReportingChain;

/**
 * An Employee's directory page as the props it renders: where they sit in
 * the company.
 */
final class DirectoryPage
{
    /**
     * The Employee's Reporting Chain: everyone above them up to one with no
     * Manager, and the Employees they are the Manager of.
     *
     * @return array{reportingChain: array<string, mixed>}
     */
    public static function props(Employee $actor, Employee $employee): array
    {
        return [
            'reportingChain' => ReportingChain::of($employee, $actor),
        ];
    }
}
