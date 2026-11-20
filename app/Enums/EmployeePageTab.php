<?php

namespace App\Enums;

enum EmployeePageTab: string
{
    case Overview = 'overview';
    case Assignments = 'assignments';
    case TimeOff = 'time-off';
    case Work = 'work';
    case Travel = 'travel';
    case Performance = 'performance';
}
