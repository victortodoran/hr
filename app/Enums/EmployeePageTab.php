<?php

namespace App\Enums;

/**
 * The tabs of an Employee's page: Overview (their record and Reporting
 * Chain), Assignments & Pay, Work Entries, and Time Off (Paid Days and Time
 * Off).
 */
enum EmployeePageTab: string
{
    case Overview = 'overview';
    case Assignments = 'assignments';
    case WorkEntries = 'work-entries';
    case TimeOff = 'time-off';
}
