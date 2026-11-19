import type { Progress, StatusBadgeState } from '@/types';

/**
 * Each state's name as people read it.
 */
export const statusLabels: Record<StatusBadgeState, string> = {
    pending: 'Pending',
    approved: 'Approved',
    declined: 'Declined',
    cancelled: 'Cancelled',
    upcoming: 'Upcoming',
    open: 'Open',
    completed: 'Completed',
    'not-started': 'Not Started',
    'on-track': 'On Track',
    'at-risk': 'At Risk',
    done: 'Done',
};

/**
 * Each state's status colour: Pending amber, Approved green, Declined red,
 * Cancelled grey, Upcoming blue, and a Performance Evaluation amber while
 * Open, since it waits on the Employee's Manager, and green once Completed.
 * A Goal's Progress is blue while Not Started, green On Track, orange At
 * Risk, since it needs attention, and grey once Done.
 */
export const statusColours: Record<StatusBadgeState, string> = {
    pending: 'bg-status-pending text-status-pending-foreground',
    approved: 'bg-status-approved text-status-approved-foreground',
    declined: 'bg-status-declined text-status-declined-foreground',
    cancelled: 'bg-status-closed text-status-closed-foreground',
    upcoming: 'bg-status-upcoming text-status-upcoming-foreground',
    open: 'bg-status-pending text-status-pending-foreground',
    completed: 'bg-status-approved text-status-approved-foreground',
    'not-started': 'bg-status-upcoming text-status-upcoming-foreground',
    'on-track': 'bg-status-approved text-status-approved-foreground',
    'at-risk': 'bg-status-attention text-status-attention-foreground',
    done: 'bg-status-closed text-status-closed-foreground',
};

/**
 * Each Progress's dot on a Goal's History timeline: the strong shade of
 * its status colour in statusColours.
 */
export const progressDotColours: Record<Progress, string> = {
    'not-started': 'bg-status-upcoming-foreground',
    'on-track': 'bg-status-approved-foreground',
    'at-risk': 'bg-status-attention-foreground',
    done: 'bg-status-closed-foreground',
};

/**
 * The classes that draw a small Select trigger as a status pill, like a
 * StatusBadge; add the state's colour from statusColours.
 */
export const statusPillTrigger =
    'gap-1 rounded-full border-0 px-2.5 py-0.5 text-xs font-medium shadow-none data-[size=sm]:h-6 [&_svg]:opacity-70 [&_svg:not([class*=size-])]:size-3.5';
