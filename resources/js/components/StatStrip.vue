<script setup lang="ts">
import StatusIcon from '@/components/StatusIcon.vue';
import type { StatusBadgeState } from '@/types';

defineProps<{
    stats: {
        label: string;
        value: string;
        state?: StatusBadgeState;
        muted?: boolean;
    }[];
    flush?: boolean;
}>();
</script>

<template>
    <dl
        data-slot="stat-strip"
        class="grid grid-cols-2 gap-x-6 gap-y-3 sm:flex sm:gap-0 sm:divide-x"
        :class="flush ? '' : 'rounded-lg border px-4 py-3'"
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
