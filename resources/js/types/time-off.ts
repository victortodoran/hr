/**
 * An Employee's Paid Days for one calendar year: that year's own number, or
 * the most recent earlier year's when it has none of its own.
 */
export type PaidDaysRecord = {
    year: number;
    days: number;
};
