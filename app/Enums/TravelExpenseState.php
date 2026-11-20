<?php

namespace App\Enums;

enum TravelExpenseState: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Declined = 'declined';

    /** @return array<int, self> */
    public static function open(): array
    {
        return [self::Pending, self::Declined];
    }
}
