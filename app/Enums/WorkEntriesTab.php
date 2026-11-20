<?php

namespace App\Enums;

use App\Support\Period;
use DateTimeInterface;

enum WorkEntriesTab: string
{
    case List = 'list';
    case Calendar = 'calendar';

    /**
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
