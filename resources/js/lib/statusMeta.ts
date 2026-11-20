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

/**
 * What a status colour means (rule S7). Each tone has one colour and one
 * icon across the app: pending waits on someone, approved is settled or
 * active, declined is refused, neutral is open with nothing waiting, over,
 * closed or cancelled, upcoming hasn't started yet, attention needs it.
 */
export type StatusTone =
    | 'pending'
    | 'approved'
    | 'declined'
    | 'neutral'
    | 'upcoming'
    | 'attention';

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
 * Each state's tone, the complete mapping of rule S9: Pending amber;
 * Approved, Completed, On Track and Done green; Declined red; Cancelled and
 * an Open Performance Evaluation grey; Upcoming and Not Started blue; At
 * Risk orange. A new state takes the tone of its meaning (S8).
 */
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
};

/**
 * Each tone's icon, which follows the colour so status never relies on
 * colour alone (S7): clock, check, cross, hollow circle, dashed circle,
 * triangle.
 */
export const toneIcons: Record<StatusTone, Component> = {
    pending: Clock,
    approved: CircleCheck,
    declined: CircleX,
    neutral: Circle,
    upcoming: CircleDashed,
    attention: TriangleAlert,
};

/**
 * Each tone's badge colours: tinted background and strong text.
 */
export const toneColours: Record<StatusTone, string> = {
    pending: 'bg-status-pending text-status-pending-foreground',
    approved: 'bg-status-approved text-status-approved-foreground',
    declined: 'bg-status-declined text-status-declined-foreground',
    neutral: 'bg-status-neutral text-status-neutral-foreground',
    upcoming: 'bg-status-upcoming text-status-upcoming-foreground',
    attention: 'bg-status-attention text-status-attention-foreground',
};

/**
 * Each tone's icon colour, for a status icon standing on the page's own
 * background (StatusIcon, a Goal's History dot).
 */
export const toneIconColours: Record<StatusTone, string> = {
    pending: 'text-status-pending-icon',
    approved: 'text-status-approved-icon',
    declined: 'text-status-declined-icon',
    neutral: 'text-status-neutral-icon',
    upcoming: 'text-status-upcoming-icon',
    attention: 'text-status-attention-icon',
};

/**
 * Each state's badge colours, through its tone.
 */
export const statusColours = Object.fromEntries(
    Object.entries(statusTones).map(([state, tone]) => [
        state,
        toneColours[tone],
    ]),
) as Record<StatusBadgeState, string>;

/**
 * Each tone's dot colour: the icon colour as a fill.
 */
const toneDotColours: Record<StatusTone, string> = {
    pending: 'bg-status-pending-icon',
    approved: 'bg-status-approved-icon',
    declined: 'bg-status-declined-icon',
    neutral: 'bg-status-neutral-icon',
    upcoming: 'bg-status-upcoming-icon',
    attention: 'bg-status-attention-icon',
};

/**
 * Each Progress's dot on a Goal's History timeline, through its tone.
 */
export const progressDotColours = Object.fromEntries(
    (['not-started', 'on-track', 'at-risk', 'done'] as const).map(
        (progress) => [progress, toneDotColours[statusTones[progress]]],
    ),
) as Record<Progress, string>;

/**
 * The classes that draw a small Select trigger as a status pill, like a
 * StatusBadge; add the state's colour from statusColours.
 */
export const statusPillTrigger =
    'gap-1 rounded-full border-0 px-2.5 py-0.5 text-caption font-medium shadow-none data-[size=sm]:h-6 [&_svg]:opacity-70 [&_svg:not([class*=size-])]:size-3.5';

/**
 * A state's icon, through its tone, for a status pill that isn't a
 * StatusBadge (a Progress Select's trigger), so it too carries the icon.
 */
export function statusIcon(state: StatusBadgeState): Component {
    return toneIcons[statusTones[state]];
}
