import type { Component } from 'vue';
import {
    Circle,
    CircleCheck,
    CircleDashed,
    CircleX,
    Clock,
    TriangleAlert,
} from '@lucide/vue';
import type { Progress, StatusBadgeState } from '@/types';

export type StatusTone =
    | 'pending'
    | 'approved'
    | 'declined'
    | 'neutral'
    | 'upcoming'
    | 'attention';

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
    'in-effect': 'In effect',
    past: 'Past',
    ended: 'Ended',
};

export const statusTones: Record<StatusBadgeState, StatusTone> = {
    pending: 'pending',
    approved: 'approved',
    declined: 'declined',
    cancelled: 'neutral',
    upcoming: 'upcoming',
    open: 'neutral',
    completed: 'approved',
    'not-started': 'upcoming',
    'on-track': 'approved',
    'at-risk': 'attention',
    done: 'approved',
    'in-effect': 'approved',
    past: 'neutral',
    ended: 'neutral',
};

/** The icon follows the colour, so status never relies on colour alone (S7). */
export const toneIcons: Record<StatusTone, Component> = {
    pending: Clock,
    approved: CircleCheck,
    declined: CircleX,
    neutral: Circle,
    upcoming: CircleDashed,
    attention: TriangleAlert,
};

export const toneColours: Record<StatusTone, string> = {
    pending: 'bg-status-pending text-status-pending-foreground',
    approved: 'bg-status-approved text-status-approved-foreground',
    declined: 'bg-status-declined text-status-declined-foreground',
    neutral: 'bg-status-neutral text-status-neutral-foreground',
    upcoming: 'bg-status-upcoming text-status-upcoming-foreground',
    attention: 'bg-status-attention text-status-attention-foreground',
};

export const toneIconColours: Record<StatusTone, string> = {
    pending: 'text-status-pending-icon',
    approved: 'text-status-approved-icon',
    declined: 'text-status-declined-icon',
    neutral: 'text-status-neutral-icon',
    upcoming: 'text-status-upcoming-icon',
    attention: 'text-status-attention-icon',
};

const toneDotColours: Record<StatusTone, string> = {
    pending: 'bg-status-pending-icon',
    approved: 'bg-status-approved-icon',
    declined: 'bg-status-declined-icon',
    neutral: 'bg-status-neutral-icon',
    upcoming: 'bg-status-upcoming-icon',
    attention: 'bg-status-attention-icon',
};

export const progressDotColours = Object.fromEntries(
    (['not-started', 'on-track', 'at-risk', 'done'] as const).map(
        (progress) => [progress, toneDotColours[statusTones[progress]]],
    ),
) as Record<Progress, string>;
