<script setup lang="ts">
import StatusIcon from '@/components/StatusIcon.vue';
import type { StatusBadgeState } from '@/types';

/**
 * Two to six numbers about the current view, in one row with hairlines
 * between them (rules L10, K10): each a value in `text-stat` and a label
 * under it, with the state's icon before the label when it counts one
 * state. A muted value is one that doesn't count, such as Declined hours.
 * On a phone the row wraps.
 */
defineProps<{
    stats: {
        label: string;
        value: string;
        state?: StatusBadgeState;
        muted?: boolean;
    }[];
}>();
</script>

<template>
    <dl
        data-slot="stat-strip"
        class="flex flex-wrap gap-x-6 gap-y-3 rounded-lg border px-4 py-3 sm:gap-0 sm:divide-x"
    >
        <div
            v-for="stat in stats"
            :key="stat.label"
            class="flex min-w-0 flex-col-reverse sm:px-5 sm:first:pl-0 sm:last:pr-0"
        >
            <dt
                class="flex items-center gap-1.5 text-caption text-muted-foreground"
            >
                <StatusIcon v-if="stat.state" :state="stat.state" labelled />
                {{ stat.label }}
            </dt>
            <dd
                class="text-stat tabular-nums"
                :class="stat.muted ? 'text-muted-foreground' : ''"
            >
                {{ stat.value }}
            </dd>
        </div>
    </dl>
</template>
