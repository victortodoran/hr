<?php

namespace App\Enums;

/**
 * Where a Travel Expense stands, like a Work Entry: Pending until its
 * Employee's Manager approves or declines it, Approved once accepted,
 * Locked for good once closed, and Declined when refused with a reason.
 */
enum TravelExpenseState: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Declined = 'declined';
    case Locked = 'locked';

    /**
     * The states in which the Employee may still change or delete it:
     * Pending, or Declined, when changing it sends it back to Pending.
     *
     * @return array<int, self>
     */
    public static function open(): array
    {
        return [self::Pending, self::Declined];
    }

    /**
     * The states in which its amount has been accepted as correct: Approved,
     * or Locked, which an Approved one becomes for good.
     *
     * @return array<int, self>
     */
    public static function accepted(): array
    {
        return [self::Approved, self::Locked];
    }
}
