<?php

namespace App\Enums;

use App\Support\Period;
use DateTimeInterface;

/**
 * The tabs of the Work Entries week page: the List of days, in any Period,
 * and the Calendar of one month.
 */
enum WorkEntriesTab: string
{
    case List = 'list';
    case Calendar = 'calendar';

    /**
     * The query to go back to after a change on a day, from the query the
     * page sent: the Calendar on the month holding the day, or the List on
     * the Period asked for while it holds the day, else the week holding
     * it.
     *
     * @param  array<array-key, mixed>  $query
     * @return array<string, string>
     */
    public static function backTo(array $query, DateTimeInterface $day): array
    {
        if (($query['tab'] ?? null) === self::Calendar->value) {
            return ['tab' => self::Calendar->value, 'month' => $day->format('Y-m')];
        }

        return Period::holding($query, $day)->query();
    }
}
