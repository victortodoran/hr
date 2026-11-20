<script setup lang="ts">
import FilterBar from '@/components/FilterBar.vue';
import FilterSelect from '@/components/FilterSelect.vue';
import type { ApprovalFilters, ChoiceToApprove, FilterChoice } from '@/types';

const props = defineProps<{
    filters: ApprovalFilters;
    employees: ChoiceToApprove[];
    projects: ChoiceToApprove[];
}>();

const emit = defineEmits<{
    change: [query: Record<string, string>];
}>();

defineSlots<{
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
