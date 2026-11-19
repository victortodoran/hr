/**
 * Every state the status badge can show. Pending, Approved and Declined are
 * Work Entry and Travel Expense states; Pending, Approved, Declined and
 * Cancelled are Time Off and Travel Request states; Upcoming is a Future
 * Employee, who hasn't started yet; Open and Completed are Performance
 * Evaluation states, and None yet an Employee who has no Performance
 * Evaluation for the year yet; Not Started, On Track, At Risk and Done are a Goal's
 * Progress.
 */
export type StatusBadgeState =
    | 'pending'
    | 'approved'
    | 'declined'
    | 'cancelled'
    | 'upcoming'
    | 'open'
    | 'completed'
    | 'not-started'
    | 'on-track'
    | 'at-risk'
    | 'done'
    | 'none-yet';
