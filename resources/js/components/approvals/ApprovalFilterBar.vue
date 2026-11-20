<script setup lang="ts">
import FilterBar from '@/components/FilterBar.vue';
import FilterSelect from '@/components/FilterSelect.vue';
import type { ApprovalFilters, ChoiceToApprove, FilterChoice } from '@/types';

/**
 * Filters for the Approvals Work Entries tab: one Employee and one Project
 * they have Pending Work Entries on, both always chosen, each offered with
 * how many Work Entries wait. Shows what the server resolved, and emits the
 * query string to reload the tab with whenever one changes; choosing
 * another Employee drops the Project, so their first one is shown.
 */
const props = defineProps<{
    filters: ApprovalFilters;
    employees: ChoiceToApprove[];
    projects: ChoiceToApprove[];
}>();

const emit = defineEmits<{
    change: [query: Record<string, string>];
}>();

defineSlots<{
    /** The matching count and total, at the bar's right (rule K5). */
    summary?: () => unknown;
}>();

function withPending(choices: ChoiceToApprove[]): FilterChoice[] {
    return choices.map((choice) => ({
        value: String(choice.id),
        label: `${choice.name} (${choice.pending})`,
    }));
}

function chooseEmployee(employee: string): void {
    emit('change', { employee });
}

function chooseProject(project: string): void {
    emit('change', {
        employee: String(props.filters.employee),
        project,
    });
}
</script>

<template>
    <FilterBar>
        <FilterSelect
            label="Employee"
            width="w-48"
            :model-value="String(filters.employee)"
            :choices="withPending(employees)"
            @update:model-value="chooseEmployee"
        />
        <FilterSelect
            label="Project"
            width="w-48"
            :model-value="String(filters.project)"
            :choices="withPending(projects)"
            @update:model-value="chooseProject"
        />
        <template v-if="$slots.summary" #summary>
            <slot name="summary" />
        </template>
    </FilterBar>
</template>
