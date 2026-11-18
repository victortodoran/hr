<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { statusColours, statusLabels } from '@/lib/status';
import { cn } from '@/lib/utils';
import type { StatusBadgeState } from '@/types';

/**
 * Shows a state in its status colour: Pending amber, Approved green, Declined
 * red, Cancelled grey, Upcoming blue, Open amber. The label defaults to
 * the state's name; pass slot content to word it differently.
 */
const props = defineProps<{
    state: StatusBadgeState;
    /** Classes merged over the badge's own, such as a width. */
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <span
        data-slot="status-badge"
        :class="
            cn(
                'inline-flex w-fit shrink-0 items-center justify-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                statusColours[state],
                props.class,
            )
        "
        ><slot>{{ statusLabels[state] }}</slot></span
    >
</template>
