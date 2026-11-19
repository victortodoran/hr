<?php

namespace App\Enums;

/**
 * What the Performance Evaluations page can narrow a year's list to: the
 * Employees whose Performance Evaluation for it is Open, those whose is
 * Completed, and those with none yet.
 */
enum PerformanceEvaluationListState: string
{
    case Open = 'open';
    case Completed = 'completed';
    case NoneYet = 'none-yet';
}
