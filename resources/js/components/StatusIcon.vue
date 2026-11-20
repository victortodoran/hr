<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import {
    statusLabels,
    statusTones,
    toneIconColours,
    toneIcons,
} from '@/lib/statusMeta';
import { cn } from '@/lib/utils';
import type { StatusBadgeState } from '@/types';

/**
 * A state as its tone's icon alone, where a badge won't fit (rule S10):
 * only where the word is visible nearby or a legend is on screen. Names
 * the state to screen readers unless something beside it does.
 */
const props = defineProps<{
    state: StatusBadgeState;
    /** Whether the state's name is already written beside the icon. */
    labelled?: boolean;
    /** Classes merged over the icon's own, such as a size. */
    class?: HTMLAttributes['class'];
}>();

const tone = computed(() => statusTones[props.state]);
</script>

<template>
    <span
        data-slot="status-icon"
        :data-tone="tone"
        :class="
            cn(
                'inline-flex shrink-0 [&>svg]:size-3.5',
                toneIconColours[tone],
                props.class,
            )
        "
        :aria-hidden="labelled ? 'true' : undefined"
        ><component :is="toneIcons[tone]" aria-hidden="true" /><span
            v-if="!labelled"
            class="sr-only"
            >{{ statusLabels[state] }}</span
        ></span
    >
</template>
