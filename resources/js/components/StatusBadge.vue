<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { StatusBadgeState } from '@/types';

/**
 * Shows a state in its status colour: Pending amber, Approved green, Declined
 * red, Locked and Cancelled grey, Upcoming blue. The label defaults to the state's name; pass
 * slot content to word it differently.
 */
defineProps<{
    state: StatusBadgeState;
}>();

const labels: Record<StatusBadgeState, string> = {
    pending: 'Pending',
    approved: 'Approved',
    declined: 'Declined',
    locked: 'Locked',
    cancelled: 'Cancelled',
    upcoming: 'Upcoming',
};

const colours: Record<StatusBadgeState, string> = {
    pending: 'bg-status-pending text-status-pending-foreground',
    approved: 'bg-status-approved text-status-approved-foreground',
    declined: 'bg-status-declined text-status-declined-foreground',
    locked: 'bg-status-closed text-status-closed-foreground',
    cancelled: 'bg-status-closed text-status-closed-foreground',
    upcoming: 'bg-status-upcoming text-status-upcoming-foreground',
};
</script>

<template>
    <span
        data-slot="status-badge"
        :class="
            cn(
                'inline-flex w-fit shrink-0 items-center justify-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                colours[state],
            )
        "
        ><slot>{{ labels[state] }}</slot></span
    >
</template>
