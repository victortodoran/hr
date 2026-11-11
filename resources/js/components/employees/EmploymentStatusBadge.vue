<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { EmploymentStatus } from '@/types';

defineProps<{
    status: EmploymentStatus;
}>();

const badges = {
    future: { label: 'Future Employee', variant: 'secondary' },
    current: { label: 'Current Employee', variant: 'default' },
    former: { label: 'Former Employee', variant: 'outline' },
} as const;
</script>

<template>
    <Badge :variant="badges[status].variant">{{ badges[status].label }}</Badge>
</template>
