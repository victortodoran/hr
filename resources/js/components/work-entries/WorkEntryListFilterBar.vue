<script setup lang="ts">
import MonthPicker from '@/components/MonthPicker.vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    Choice,
    EmployeeWithWorkEntries,
    WorkEntryListFilters,
    WorkEntryState,
} from '@/types';

/**
 * Filters for the Work Entries page: a month; one Employee with Work
 * Entries that month, each offered with how many, or any Employee; a
 * Project they have Work Entries on, or any; and a state, or any. Shows what
 * the server resolved, and emits the query string to reload the list with
 * whenever one changes. Changing the month or state keeps "Any Employee"
 * but otherwise lets the server pick the first Employee; changing anything
 * but the Project drops the Project. With nobody to choose, only the month
 * and state are offered.
 */
const props = defineProps<{
    filters: WorkEntryListFilters;
    employees: EmployeeWithWorkEntries[];
    projects: Choice[];
}>();

const emit = defineEmits<{
    change: [query: Record<string, string>];
}>();

const states: { value: WorkEntryState; label: string }[] = [
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'locked', label: 'Locked' },
];

function query(
    changes: Partial<
        Record<'month' | 'employee' | 'project' | 'state', string>
    >,
): Record<string, string> {
    const state = 'state' in changes ? changes.state : props.filters.state;

    return {
        month: changes.month ?? props.filters.month,
        ...(changes.employee ? { employee: changes.employee } : {}),
        ...(changes.project ? { project: changes.project } : {}),
        ...(state && state !== 'all' ? { state } : {}),
    };
}

/** "all" when any Employee is chosen, kept across months and states. */
const anyEmployee = (): string | undefined =>
    props.filters.employee === 'all' ? 'all' : undefined;

function chooseMonth(month: string): void {
    emit('change', query({ month, employee: anyEmployee() }));
}

function chooseState(state: unknown): void {
    emit('change', query({ state: String(state), employee: anyEmployee() }));
}

function chooseEmployee(employee: unknown): void {
    emit('change', query({ employee: String(employee) }));
}

function chooseProject(project: unknown): void {
    emit(
        'change',
        query({
            employee: String(props.filters.employee),
            ...(project !== 'all' ? { project: String(project) } : {}),
        }),
    );
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-3">
        <MonthPicker :month="filters.month" @change="chooseMonth" />
        <template v-if="employees.length > 0">
            <Select
                :model-value="String(filters.employee)"
                @update:model-value="chooseEmployee"
            >
                <SelectTrigger class="w-48" aria-label="Employee">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Any Employee</SelectItem>
                    <SelectItem
                        v-for="choice in employees"
                        :key="choice.id"
                        :value="String(choice.id)"
                        >{{ choice.name }} ({{ choice.count }})</SelectItem
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
        </template>
        <Select
            :model-value="filters.state ?? 'all'"
            @update:model-value="chooseState"
        >
            <SelectTrigger class="w-36" aria-label="State">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="all">Any state</SelectItem>
                <SelectItem
                    v-for="choice in states"
                    :key="choice.value"
                    :value="choice.value"
                    >{{ choice.label }}</SelectItem
                >
            </SelectContent>
        </Select>
    </div>
</template>
