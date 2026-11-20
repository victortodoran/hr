<?php

namespace App\Pages\TimeOff;

/**
 * The sheet for requesting Time Off as the props it adds to the Time Off
 * page.
 */
final class TimeOffFormPage
{
    /**
     * That the sheet is open, and today, the earliest first date.
     *
     * @return array{requesting: true, today: string}
     */
    public static function create(): array
    {
        return [
            'requesting' => true,
            'today' => today()->toDateString(),
        ];
    }
}
