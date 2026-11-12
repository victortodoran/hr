/**
 * The signed-in Employee's hours this week, Monday to Sunday, and how many of
 * its Work Entries are still Pending.
 */
export type HoursThisWeek = {
    /** The week's Monday. */
    week: string;
    hours: number;
    pending: number;
};

/**
 * Paid Days left in a year: Paid Days minus Pending and Approved Paid Time Off.
 */
export type PaidDaysLeft = {
    year: number;
    days: number;
};
