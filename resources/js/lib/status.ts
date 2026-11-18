import type { StatusBadgeState } from '@/types';

/**
 * Each state's name as people read it.
 */
export const statusLabels: Record<StatusBadgeState, string> = {
    pending: 'Pending',
    approved: 'Approved',
    declined: 'Declined',
    cancelled: 'Cancelled',
    upcoming: 'Upcoming',
};

/**
 * Each state's status colour: Pending amber, Approved green, Declined red,
 * Cancelled grey, Upcoming blue.
 */
export const statusColours: Record<StatusBadgeState, string> = {
    pending: 'bg-status-pending text-status-pending-foreground',
    approved: 'bg-status-approved text-status-approved-foreground',
    declined: 'bg-status-declined text-status-declined-foreground',
    cancelled: 'bg-status-closed text-status-closed-foreground',
    upcoming: 'bg-status-upcoming text-status-upcoming-foreground',
};
