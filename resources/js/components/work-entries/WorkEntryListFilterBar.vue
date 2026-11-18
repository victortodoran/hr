<script setup lang="ts">
import { computed } from 'vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import PeriodNavigation from '@/components/work-entries/PeriodNavigation.vue';
import type {
    Choice,
    EmployeeWithWorkEntries,
    WorkEntryListFilters,
    WorkEntryState,
} from '@/types';

/**
 * Filters for the Work Entries page, and the Travel Expenses page, which
 * lists Travel Expenses the same way: a Period, a week, a month or a custom
 * one, never after today; one Employee with Work Entries in it, each
 * offered with how many, or any Employee; a
 * Project they have Work Entries on, or any; and a state, or any. Shows what
 * the server resolved, and emits the query string to reload the list with
 * whenever one changes. Changing the Period or state keeps "Any Employee"
 * but otherwise lets the server pick the first Employee; changing anything
 * but the Project drops the Project. With nobody to choose, only the Period
 * and state are offered. An Employee with Work Entries on only one Project
 * shows that Project, without "Any Project" to choose; both list the same
 * Work Entries, so the filter itself stays as it was.
 */
const props = defineProps<{
    filters: WorkEntryListFilters;
    today: string;
    employees: EmployeeWithWorkEntries[];
    projects: Choice[];
}>();

const emit = defineEmits<{
    change: [query: Record<string, string>];
}>();

const states: { value: WorkEntryState; label: string }[] = [
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'declined', label: 'Declined' },
];

function query(
    changes: Partial<Record<'employee' | 'project' | 'state', string>> & {
        period?: Record<string, string>;
    },
): Record<string, string> {
    const state = 'state' in changes ? changes.state : props.filters.state;

    return {
        ...(changes.period ?? props.filters.period.query),
        ...(changes.employee ? { employee: changes.employee } : {}),
        ...(changes.project ? { project: changes.project } : {}),
        ...(state && state !== 'all' ? { state } : {}),
    };
}

/** "all" when any Employee is chosen, kept across Periods and states. */
const anyEmployee = (): string | undefined =>
    props.filters.employee === 'all' ? 'all' : undefined;

function choosePeriod(period: Record<string, string>): void {
    emit('change', query({ period, employee: anyEmployee() }));
}

/** Whether the one Employee shown has Work Entries on just one Project. */
const hasOnlyOneProject = computed(
    () =>
        typeof props.filters.employee === 'number' &&
        props.projects.length === 1,
);

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
        <PeriodNavigation
            :period="filters.period"
            :today="today"
            no-future
            @change="choosePeriod"
        />
        <div class="flex flex-wrap items-center gap-3">
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
                    :model-value="
                        hasOnlyOneProject
                            ? String(projects[0].id)
                            : (filters.project?.toString() ?? 'all')
                    "
                    @update:model-value="chooseProject"
                >
                    <SelectTrigger class="w-48" aria-label="Project">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-if="!hasOnlyOneProject" value="all"
                            >Any Project</SelectItem
                        >
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
    </div>
</template>
