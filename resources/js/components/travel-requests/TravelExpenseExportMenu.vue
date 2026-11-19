<script setup lang="ts">
import ExportMenu from '@/components/ExportMenu.vue';
import { show } from '@/routes/travel-expense-exports';
import type { TravelRequestListFilters } from '@/types';

/**
 * Exporting, from the Travel Requests page, the Approved Travel Expenses
 * paid in its Period for its Employee and Project, as a CSV or Excel file
 * to pay them back. The page's state filter is about Travel Requests, so
 * the file leaves it out.
 */
const props = defineProps<{
    filters: TravelRequestListFilters;
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
            format,
        },
    });
}
</script>

<template>
    <ExportMenu :file-url="fileUrl" label="Export expenses" />
</template>
