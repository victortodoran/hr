<script setup lang="ts">
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { ApprovalFilters, Choice, EmployeeToApprove } from '@/types';

/**
 * Filters for the Approvals Work Entries tab: one Employee, always chosen,
 * each offered with how many Work Entries of theirs wait, and a Project they
 * have Pending Work Entries on, or any. Shows what the server resolved, and
 * emits the query string to reload the tab with whenever one changes;
 * choosing another Employee drops the Project.
 */
const props = defineProps<{
    filters: ApprovalFilters;
    employees: EmployeeToApprove[];
    projects: Choice[];
}>();

const emit = defineEmits<{
    change: [query: Record<string, string>];
}>();

function chooseEmployee(employee: unknown): void {
    emit('change', { employee: String(employee) });
}

function chooseProject(project: unknown): void {
    emit('change', {
        employee: String(props.filters.employee),
        ...(project !== 'all' ? { project: String(project) } : {}),
    });
}
</script>

<template>
    <div class="flex flex-wrap items-end gap-3">
        <Select
            :model-value="String(filters.employee)"
            @update:model-value="chooseEmployee"
        >
            <SelectTrigger class="w-48" aria-label="Employee">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem
                    v-for="choice in employees"
                    :key="choice.id"
                    :value="String(choice.id)"
                    >{{ choice.name }} ({{ choice.pending }})</SelectItem
                >
            </SelectContent>
        </Select>
        <Select
            :model-value="filters.project?.toString() ?? 'all'"
            @update:model-value="chooseProject"
        >
            <SelectTrigger class="w-48" aria-label="Project">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="all">Any Project</SelectItem>
                <SelectItem
                    v-for="choice in projects"
                    :key="choice.id"
                    :value="String(choice.id)"
                    >{{ choice.name }}</SelectItem
                >
            </SelectContent>
        </Select>
    </div>
</template>
