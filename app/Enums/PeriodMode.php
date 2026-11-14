<?php

namespace App\Enums;

/**
 * How the Work Entries week page steps through time: a week from Monday to
 * Sunday, a calendar month, or a custom period from one date to another.
 */
enum PeriodMode: string
{
    case Week = 'week';
    case Month = 'month';
    case Custom = 'custom';
}
