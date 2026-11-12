/**
 * An Employee's Paid Days for one calendar year: that year's own number, or
 * the most recent earlier year's when it has none of its own.
 */
export type PaidDaysRecord = {
    year: number;
    days: number;
};

/**
 * Where Time Off stands: Pending until the Manager decides, Approved or
 * Declined once they have, or Cancelled by the Employee before it started.
 */
export type TimeOffState = 'pending' | 'approved' | 'declined' | 'cancelled';

/**
 * One of the signed-in Employee's Time Off, with the weekdays it counts and
 * whether they may still cancel it.
 */
export type TimeOffRecord = {
    id: number;
    first_date: string;
    last_date: string;
    /** The weekdays it counts; weekends never do. */
    days: number;
    is_paid: boolean;
    state: TimeOffState;
    can_cancel: boolean;
};
