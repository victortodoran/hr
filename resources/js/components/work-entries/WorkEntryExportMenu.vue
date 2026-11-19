<script setup lang="ts">
import ExportMenu from '@/components/ExportMenu.vue';
import { show } from '@/routes/work-entry-exports';
import type { WorkEntryListFilters } from '@/types';

/**
 * Exporting the Work Entries page as a CSV or Excel file: every Work Entry
 * matching its filters, across all its pages, downloaded straight away.
 * Disabled when nothing matches.
 */
const props = defineProps<{
    filters: WorkEntryListFilters;
    disabled: boolean;
}>();

/** The file's address, with the filters as the server resolved them. */
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
