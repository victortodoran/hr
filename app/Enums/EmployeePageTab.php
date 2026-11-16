<?php

namespace App\Enums;

/**
 * The tabs of an Employee's page: Overview (their record and Reporting
 * Chain), Assignments & Pay, Time Off (Paid Days and Time Off), and Travel
 * (Travel Requests and Travel Expenses).
 */
enum EmployeePageTab: string
{
    case Overview = 'overview';
    case Assignments = 'assignments';
    case TimeOff = 'time-off';
    case Travel = 'travel';
}
