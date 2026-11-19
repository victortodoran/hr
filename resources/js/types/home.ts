/**
 * The signed-in Employee's hours this month, leaving out Declined Work
 * Entries, and how many of those hours are still Pending.
 */
export type HoursThisMonth = {
    /** The month, as YYYY-MM. */
    month: string;
    hours: number;
    /** How many of those hours are still Pending. */
    pendingHours: number;
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

/** A Declined Work Entry of the signed-in Employee, to change or delete. */
export type DeclinedWorkEntryItem = {
    kind: 'work-entry';
    id: number;
    date: string;
    /** The name of the Project of its Assignment. */
    project: string;
    hours: number;
    decline_reason: string;
};

/** A Declined Travel Request of the signed-in Employee still to come. */
export type DeclinedTravelRequestItem = {
    kind: 'travel-request';
    id: number;
    destination: string;
    first_date: string;
    last_date: string;
    decline_reason: string;
};

/** A Declined Travel Expense of the signed-in Employee, to change or delete. */
export type DeclinedTravelExpenseItem = {
    kind: 'travel-expense';
    id: number;
    travel_request_id: number;
    /** Its Travel Request's destination. */
    destination: string;
    description: string;
    decline_reason: string;
};

/** Declined Time Off of the signed-in Employee still to come. */
export type DeclinedTimeOffItem = {
    kind: 'time-off';
    id: number;
    first_date: string;
    last_date: string;
    decline_reason: string;
};

/** One thing of the signed-in Employee's that was declined. */
export type NeedsAttentionItem =
    | DeclinedWorkEntryItem
    | DeclinedTravelRequestItem
    | DeclinedTravelExpenseItem
    | DeclinedTimeOffItem;

/**
 * What the signed-in Employee should look at again: the first few of each
 * kind, and how many more there are.
 */
export type NeedsAttention = {
    items: NeedsAttentionItem[];
    more: number;
};
