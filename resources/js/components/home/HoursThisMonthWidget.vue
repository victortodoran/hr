<script setup lang="ts">
import { Clock } from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import HomeWidget from '@/components/home/HomeWidget.vue';
import StatStrip from '@/components/StatStrip.vue';
import { formatHours } from '@/lib/work-entries';
import { index as workEntries } from '@/routes/work-entries';
import type { HoursThisMonth } from '@/types';

const props = defineProps<{
    hoursThisMonth: HoursThisMonth;
}>();

const stats = computed(() => [
    {
        label: 'Logged',
        value: formatHours(props.hoursThisMonth.hours),
    },
    {
        label: 'Pending',
        value: formatHours(props.hoursThisMonth.pendingHours),
        state: 'pending' as const,
    },
    {
        label: 'Approved',
        value: formatHours(
            props.hoursThisMonth.hours - props.hoursThisMonth.pendingHours,
        ),
        state: 'approved' as const,
    },
]);
</script>

<template>
    <HomeWidget
        title="Hours this month"
        :href="workEntries({ query: { month: hoursThisMonth.month } })"
        link-label="Open Work"
    >
        <EmptyState
            v-if="hoursThisMonth.hours === 0"
            :icon="Clock"
            message="No hours this month yet. Declined ones don't count."
        />
        <StatStrip v-else :stats="stats" flush />
    </HomeWidget>
</template>
