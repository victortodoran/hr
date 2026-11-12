<?php

namespace App\Enums;

/**
 * Where Time Off stands: Pending until the Employee's Manager approves or
 * declines it, Approved or Declined once they have, and Cancelled once the
 * Employee withdrew it before its first day.
 */
enum TimeOffState: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Declined = 'declined';
    case Cancelled = 'cancelled';

    /**
     * The states in which Time Off still stands: it uses Paid Days and no
     * other Time Off may overlap it.
     *
     * @return array<int, self>
     */
    public static function standing(): array
    {
        return [self::Pending, self::Approved];
    }
}
