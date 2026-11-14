import type { StatusBadgeState } from '@/types';

/**
 * Each state's status colour, as background and text classes: Pending
 * amber, Approved green, Declined red, Locked and Cancelled grey, Upcoming
 * blue. Shared by the status badge and anything else coloured by state.
 */
export const statusColours: Record<StatusBadgeState, string> = {
    pending: 'bg-status-pending text-status-pending-foreground',
    approved: 'bg-status-approved text-status-approved-foreground',
    declined: 'bg-status-declined text-status-declined-foreground',
    locked: 'bg-status-closed text-status-closed-foreground',
    cancelled: 'bg-status-closed text-status-closed-foreground',
    upcoming: 'bg-status-upcoming text-status-upcoming-foreground',
};
