<script setup lang="ts">
import { Download } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="outline" :disabled="disabled">
                <Download />
                Export
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <DropdownMenuItem as-child>
                <a :href="fileUrl('csv')" download>CSV</a>
            </DropdownMenuItem>
            <DropdownMenuItem as-child>
                <a :href="fileUrl('xlsx')" download>Excel</a>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
