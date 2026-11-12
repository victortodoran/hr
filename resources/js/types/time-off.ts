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
 * One of the signed-in Employee's Time Off, with the weekdays it counts, who
 * decided it and why it was declined, and whether they may still cancel it.
 */
export type TimeOffRecord = {
    id: number;
    first_date: string;
    last_date: string;
    /** The weekdays it counts; weekends never do. */
    days: number;
    is_paid: boolean;
    state: TimeOffState;
    /** The name of the Manager who approved or declined it, once decided. */
    decided_by: string | null;
    /** Why it was declined; only Declined Time Off has one. */
    decline_reason: string | null;
    can_cancel: boolean;
};

/**
 * A Direct Report's Pending Time Off for their Manager to approve or
 * decline, with that Employee's Paid Days left in each year it touches.
 */
export type TimeOffToApprove = {
    id: number;
    employee: { id: number; name: string };
    first_date: string;
    last_date: string;
    /** The weekdays it counts; weekends never do. */
    days: number;
    is_paid: boolean;
    /** Paid Days left per year, already counting this Time Off when Paid. */
    paid_days_left: { year: number; days: number }[];
};
