/**
 * The days a page shows, such as the Work Entries week page or a list
 * filtered by Period: a week from Monday to Sunday, a calendar month, or a
 * custom period, from and to as YYYY-MM-DD.
 */
export type Period = {
    mode: 'week' | 'month' | 'custom';
    from: string;
    to: string;
    /** The URL query asking for the Period, kept when saving or deleting. */
    query: Record<string, string>;
};
