<?php

namespace App\Pages\TimeOff;

/**
 * The form for requesting Time Off as the props it renders.
 */
final class TimeOffFormPage
{
    /**
     * Today, the earliest first date.
     *
     * @return array{today: string}
     */
    public static function create(): array
    {
        return [
            'today' => today()->toDateString(),
        ];
    }
}
