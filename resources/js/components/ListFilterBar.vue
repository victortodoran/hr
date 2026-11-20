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

const props = withDefaults(
    defineProps<{
        filters: ListFilters<string>;
        today: string;
        employees: EmployeeWithCount[];
        projects?: Choice[];
        states: StateChoice[];
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

const anyEmployee = (): string | undefined =>
    props.filters.employee === 'all' ? 'all' : undefined;

function choosePeriod(period: Record<string, string>): void {
    emit('change', query({ period, employee: anyEmployee() }));
}

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
