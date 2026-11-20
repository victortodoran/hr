/** `from` and `to` as YYYY-MM-DD. */
export type Period = {
    mode: 'week' | 'month' | 'custom';
    from: string;
    to: string;
    /** The URL query asking for the Period, kept when saving or deleting. */
    query: Record<string, string>;
};
