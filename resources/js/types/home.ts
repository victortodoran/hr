/**
 * The signed-in Employee's hours this month, leaving out Declined Work
 * Entries, and how many of its Work Entries are still Pending.
 */
export type HoursThisMonth = {
    /** The month, as YYYY-MM. */
    month: string;
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

/** One month of the signed-in Employee's hours this year. */
export type MonthOfHours = {
    /** The month, as YYYY-MM. */
    month: string;
    /** Hours of the month's Work Entries that aren't Declined. */
    hours: number;
    /** How many of those hours are still Pending. */
    pendingHours: number;
};

/**
 * The signed-in Employee's hours this year, month by month from January, or
 * their Start Date's month, to this month, leaving out Declined Work
 * Entries, with how many are still Pending.
 */
export type HoursThisYear = {
    year: number;
    hours: number;
    pendingHours: number;
    months: MonthOfHours[];
};
