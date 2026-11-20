<script setup lang="ts">
import ExportMenu from '@/components/ExportMenu.vue';
import { show } from '@/routes/travel-expense-exports';
import type { TravelRequestListFilters } from '@/types';

/** The page's state filter is about Travel Requests, so the file leaves it out. */
const props = defineProps<{
    filters: TravelRequestListFilters;
}>();

function fileUrl(format: 'csv' | 'xlsx'): string {
    return show.url({
        query: {
            ...props.filters.period.query,
            employee: String(props.filters.employee ?? 'all'),
            ...(props.filters.project
                ? { project: props.filters.project }
                : {}),
            format,
        },
    });
}
</script>

<template>
    <ExportMenu :file-url="fileUrl" label="Export expenses" />
</template>
