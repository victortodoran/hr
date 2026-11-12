/**
 * Every state the status badge can show. Pending, Approved and Locked are Work
 * Entry states; Pending, Approved, Declined and Cancelled are Time Off states;
 * Upcoming is a Future Employee, who hasn't started yet.
 */
export type StatusBadgeState =
    | 'pending'
    | 'approved'
    | 'declined'
    | 'locked'
    | 'cancelled'
    | 'upcoming';
