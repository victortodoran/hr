/**
 * Every state the status badge can show. Pending, Approved and Locked are Work
 * Entry states; Pending, Approved, Declined and Cancelled are Time Off states.
 */
export type StatusBadgeState =
    | 'pending'
    | 'approved'
    | 'declined'
    | 'locked'
    | 'cancelled';
