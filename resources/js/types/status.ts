/**
 * Every state the status badge can show. Pending, Approved and Declined are
 * Work Entry and Travel Expense states; Pending, Approved, Declined and
 * Cancelled are Time Off and Travel Request states; Upcoming is a Future
 * Employee, who hasn't started yet.
 */
export type StatusBadgeState =
    | 'pending'
    | 'approved'
    | 'declined'
    | 'cancelled'
    | 'upcoming'
    | 'open'
    | 'completed';
