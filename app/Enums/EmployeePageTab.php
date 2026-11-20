<?php

namespace App\Enums;

/**
 * The tabs of an Employee's page: Overview (their record and Reporting
 * Chain), Assignments & Pay, Time Off (Paid Days and Time Off), and their
 * Work Entries, Travel Requests and Performance Evaluations.
 */
enum EmployeePageTab: string
{
    case Overview = 'overview';
    case Assignments = 'assignments';
    case TimeOff = 'time-off';
    case Work = 'work';
    case Travel = 'travel';
    case Performance = 'performance';
}
