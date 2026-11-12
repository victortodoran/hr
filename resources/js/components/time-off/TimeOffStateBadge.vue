<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { TimeOffState } from '@/types';

defineProps<{
    state: TimeOffState;
}>();

const stateNames: Record<TimeOffState, string> = {
    pending: 'Pending',
    approved: 'Approved',
    declined: 'Declined',
    cancelled: 'Cancelled',
};
</script>

<template>
    <Badge variant="outline">{{ stateNames[state] }}</Badge>
</template>
