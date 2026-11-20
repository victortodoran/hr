<script setup lang="ts">
import ExportMenu from '@/components/ExportMenu.vue';
import { show } from '@/routes/work-entry-exports';
import type { WorkEntryListFilters } from '@/types';

const props = defineProps<{
    filters: WorkEntryListFilters;
    disabled: boolean;
}>();

function fileUrl(format: 'csv' | 'xlsx'): string {
    return show.url({
        query: {
            ...props.filters.period.query,
            employee: String(props.filters.employee ?? 'all'),
            ...(props.filters.project
                ? { project: props.filters.project }
                : {}),
            ...(props.filters.state ? { state: props.filters.state } : {}),
            format,
        },
    });
}
</script>

<template>
    <ExportMenu :file-url="fileUrl" :disabled="disabled" />
</template>
