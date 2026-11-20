<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import {
    statusLabels,
    statusTones,
    toneColours,
    toneIcons,
} from '@/lib/statusMeta';
import { cn } from '@/lib/utils';
import type { StatusBadgeState } from '@/types';

/**
 * Shows a state as its tone's icon and its word (rules S6, S7): the tone
 * and icon come from statusMeta, so colour never carries the state alone.
 * The label defaults to the state's name; pass slot content to word it
 * differently.
 */
const props = defineProps<{
    state: StatusBadgeState;
    /** Classes merged over the badge's own, such as a width. */
    class?: HTMLAttributes['class'];
}>();

const tone = computed(() => statusTones[props.state]);
</script>

<template>
    <span
        data-slot="status-badge"
        :data-tone="tone"
        :class="
            cn(
                'inline-flex w-fit shrink-0 items-center justify-center gap-1 rounded-full py-0.5 pr-2 pl-1.5 text-caption font-medium whitespace-nowrap',
                toneColours[tone],
                props.class,
            )
        "
        ><component
            :is="toneIcons[tone]"
            class="size-3.5 shrink-0"
            aria-hidden="true"
        /><slot>{{ statusLabels[state] }}</slot></span
    >
</template>
