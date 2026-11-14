<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { statusColours, statusLabels } from '@/lib/status';
import { cn } from '@/lib/utils';
import type { StatusBadgeState } from '@/types';

/**
 * A state as a small dot in its status colour, where a badge won't fit,
 * naming the state to screen readers unless something beside it does.
 */
const props = defineProps<{
    state: StatusBadgeState;
    /** Whether the state's name is already written beside the dot. */
    labelled?: boolean;
    /** Classes merged over the dot's own, such as a size. */
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <span
        data-slot="status-dot"
        :class="
            cn(
                'inline-block size-2 shrink-0 rounded-full ring-1 ring-current',
                statusColours[state],
                props.class,
            )
        "
        :aria-hidden="labelled ? 'true' : undefined"
        ><span v-if="!labelled" class="sr-only">{{
            statusLabels[state]
        }}</span></span
    >
</template>
