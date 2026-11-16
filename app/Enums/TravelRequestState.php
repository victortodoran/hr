<?php

namespace App\Enums;

/**
 * Where a Travel Request stands: Pending until the Employee's Manager
 * approves or declines it, Approved or Declined once they have, and
 * Cancelled once the Employee withdrew it before its first day.
 */
enum TravelRequestState: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Declined = 'declined';
    case Cancelled = 'cancelled';

    /**
     * The states in which a Travel Request still stands: no other Travel
     * Request or Time Off of the Employee may share a day with it.
     *
     * @return array<int, self>
     */
    public static function standing(): array
    {
        return [self::Pending, self::Approved];
    }
}
