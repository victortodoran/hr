/**
 * Every state the status badge can show. Pending, Approved and Declined are
 * Work Entry and Travel Expense states; Pending, Approved, Declined and
 * Cancelled are Time Off and Travel Request states; Upcoming is a Future
 * Employee, who hasn't started yet; Open and Completed are Performance
 * Evaluation states; Not Started, On Track, At Risk and Done are a Goal's
 * Progress; In effect, Upcoming and Past are a Pay change's, and Ended an
 * Assignment's that ended before today.
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
    | 'in-effect'
    | 'past'
    | 'ended';
