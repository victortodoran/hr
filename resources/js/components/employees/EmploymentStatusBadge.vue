<script setup lang="ts">
import StatusBadge from '@/components/StatusBadge.vue';
import type { EmploymentStatus, StatusBadgeState } from '@/types';

defineProps<{
    status: EmploymentStatus;
}>();

const badges: Record<
    EmploymentStatus,
    { label: string; state: StatusBadgeState }
> = {
    future: { label: 'Future Employee', state: 'upcoming' },
    current: { label: 'Current Employee', state: 'approved' },
    former: { label: 'Former Employee', state: 'cancelled' },
};
</script>

<template>
    <StatusBadge :state="badges[status].state">{{
        badges[status].label
    }}</StatusBadge>
</template>
