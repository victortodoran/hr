<?php

namespace App\Enums;

/**
 * The tabs of the Work Entries week page: the List of days, in any Period,
 * and the Calendar of one month.
 */
enum WorkEntriesTab: string
{
    case List = 'list';
    case Calendar = 'calendar';
}
