<script setup lang="ts">
import { computed } from 'vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterSelect from '@/components/FilterSelect.vue';
import PeriodNavigation from '@/components/PeriodNavigation.vue';
import type {
    Choice,
    EmployeeWithCount,
    FilterChoice,
    ListFilters,
    StateChoice,
} from '@/types';

/**
 * Filters for a list across Employees, such as the Work Entries and Travel
 * Requests pages: a Period, a week, a month or a custom one, never after
 * today unless `allowFuture` is set; one Employee with something listed in
 * it, each offered with how many, or any Employee; a Project they have
 * something on, or any, unless the list gives no `projects` to choose
 * from; and one of the list's `states`, or any. Shows what
 * the server resolved, and emits the query string to reload the list with
 * whenever one changes. Changing the Period or state keeps "Any Employee"
 * but otherwise lets the server pick; changing anything but the Project
 * drops the Project. With nobody to choose, only the Period and state are
 * offered. An Employee with something on only one Project shows that
 * Project, without "Any Project" to choose; both list the same, so the
 * filter itself stays as it was. Built on `FilterBar`, like every list's
 * filters, so a list across Employees only adds what else it needs.
 */
const props = withDefaults(
    defineProps<{
        filters: ListFilters<string>;
        today: string;
        employees: EmployeeWithCount[];
        /** Left out on a list not filtered by Project, such as Time Off. */
        projects?: Choice[];
        /** The states the list can be filtered by. */
        states: StateChoice[];
        /** Lets the Period go past today, for what is planned ahead. */
        allowFuture?: boolean;
    }>(),
    { allowFuture: false },
);

const emit = defineEmits<{
    change: [query: Record<string, string>];
}>();

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

/** Whether the one Employee shown has something on just one Project. */
const hasOnlyOneProject = computed(
    () =>
        typeof props.filters.employee === 'number' &&
        props.projects?.length === 1,
);

const employeeChoices = computed<FilterChoice[]>(() =>
    props.employees.map((choice) => ({
        value: String(choice.id),
        label: `${choice.name} (${choice.count})`,
    })),
);

const projectChoices = computed<FilterChoice[]>(() =>
    (props.projects ?? []).map((choice) => ({
        value: String(choice.id),
        label: choice.name,
    })),
);

function chooseState(state: string): void {
    emit('change', query({ state, employee: anyEmployee() }));
}

function chooseEmployee(employee: string): void {
    emit('change', query({ employee }));
}

function chooseProject(project: string): void {
    emit(
        'change',
        query({
            employee: String(props.filters.employee),
            ...(project !== 'all' ? { project } : {}),
        }),
    );
}
</script>

<template>
    <FilterBar>
        <PeriodNavigation
            :period="filters.period"
            :today="today"
            :no-future="!allowFuture"
            @change="choosePeriod"
        />
        <template v-if="employees.length > 0">
            <FilterSelect
                label="Employee"
                any="Any Employee"
                width="w-48"
                :model-value="String(filters.employee)"
                :choices="employeeChoices"
                @update:model-value="chooseEmployee"
            />
            <FilterSelect
                v-if="projects"
                label="Project"
                :any="hasOnlyOneProject ? undefined : 'Any Project'"
                width="w-48"
                :model-value="
                    hasOnlyOneProject
                        ? String(projects[0].id)
                        : (filters.project?.toString() ?? 'all')
                "
                :choices="projectChoices"
                @update:model-value="chooseProject"
            />
        </template>
        <FilterSelect
            label="State"
            any="Any state"
            width="w-36"
            :model-value="filters.state ?? 'all'"
            :choices="states"
            @update:model-value="chooseState"
        />
    </FilterBar>
</template>
