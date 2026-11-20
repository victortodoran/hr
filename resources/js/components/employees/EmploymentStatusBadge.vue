<script setup lang="ts">
import StatusBadge from '@/components/StatusBadge.vue';
import type { EmploymentStatus, StatusBadgeState } from '@/types';

defineProps<{
    status: EmploymentStatus;
    short?: boolean;
}>();

const badges: Record<
    EmploymentStatus,
    { label: string; state: StatusBadgeState }
> = {
    future: { label: 'Future', state: 'upcoming' },
    current: { label: 'Current', state: 'approved' },
    former: { label: 'Former', state: 'cancelled' },
};
</script>

<template>
    <StatusBadge :state="badges[status].state">{{
        short ? badges[status].label : `${badges[status].label} Employee`
    }}</StatusBadge>
</template>
