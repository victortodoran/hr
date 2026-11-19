/**
 * Where Time Off stands: Pending until the Manager decides, Approved or
 * Declined once they have, or Cancelled by the Employee before it started.
 */
export type TimeOffState = 'pending' | 'approved' | 'declined' | 'cancelled';

/**
 * One Time Off as a list shows it: its dates, the weekdays it counts, who
 * decided it and why it was declined.
 */
export type TimeOffDetails = {
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
};

/**
 * One of the signed-in Employee's Time Off, with whether they may still
 * cancel it.
 */
export type TimeOffRecord = TimeOffDetails & {
    can_cancel: boolean;
};

/**
 * Paid Days left in a year: Paid Days minus Pending and Approved Paid Time Off.
 */
export type PaidDaysLeftInYear = {
    year: number;
    days: number;
};

/**
 * An Employee's Time Off as their page shows it: their Paid Days, Paid Days
 * left this year and, from October, next year, and all their Time Off,
 * latest first.
 */
export type EmployeeTimeOff = {
    paid_days: number;
    paid_days_left: PaidDaysLeftInYear[];
    requests: TimeOffDetails[];
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
    paid_days_left: PaidDaysLeftInYear[];
};

/**
 * What requesting Time Off would take: its weekdays and, when Paid, for
 * each year it touches the Paid Days it would use and those left after it,
 * counting Pending and Approved Paid Time Off.
 */
export type TimeOffPreview = {
    weekdays: number;
    years: { year: number; uses: number; left_after: number }[];
};
