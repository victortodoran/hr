<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronRight, Plus, UsersRound } from '@lucide/vue';
import { useMediaQuery } from '@vueuse/core';
import { computed } from 'vue';
import DataTable from '@/components/DataTable.vue';
import EmploymentStatusBadge from '@/components/employees/EmploymentStatusBadge.vue';
import EmptyState from '@/components/EmptyState.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterSearch from '@/components/FilterSearch.vue';
import FilterSelect from '@/components/FilterSelect.vue';
import PageHeader from '@/components/PageHeader.vue';
import PageContainer from '@/components/PageContainer.vue';
import Pagination from '@/components/Pagination.vue';
import PersonCell from '@/components/PersonCell.vue';
import SegmentedControl from '@/components/SegmentedControl.vue';
import { Button } from '@/components/ui/button';
import ValueOr from '@/components/ValueOr.vue';
import { useListFilters } from '@/composables/useListFilters';
import { formatDate } from '@/lib/dates';
import { create, index, show } from '@/routes/employees';
import type {
    Choice,
    EmployeeListEntry,
    EmploymentStatus,
    EmploymentStatusCounts,
    FilterChoice,
    Paginated,
    TableColumn,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Employees', href: index() }],
    },
});

/**
 * Every Employee whose record the viewer may see (rule L11), filtered by
 * status chips with their counts, part of a name, a Department and a
 * Manager. A row opens the Employee's page; Add Employee, the one primary,
 * sits by the title.
 */
const props = defineProps<{
    employees: Paginated<EmployeeListEntry>;
    search: string;
    status: EmploymentStatus | null;
    department: number | null;
    manager: number | null;
    counts: EmploymentStatusCounts;
    /** The Departments of the Employees the viewer may see. */
    departments: Choice[];
    /** The Managers of the Employees the viewer may see. */
    managers: Choice[];
    canAdd: boolean;
}>();

const columns: TableColumn[] = [
    { key: 'name', label: 'Name', phone: 'primary' },
    { key: 'department', label: 'Department', minor: true },
    { key: 'manager', label: 'Manager', minor: true },
    { key: 'start_date', label: 'Start Date', minor: true },
    { key: 'status', label: 'Status', phone: 'end' },
    { key: 'open', label: '', align: 'end', minor: true },
];

const statusOrder = ['all', 'current', 'future', 'former'] as const;

const statusLabels: Record<(typeof statusOrder)[number], string> = {
    all: 'All',
    current: 'Current',
    future: 'Future',
    former: 'Former',
};

const statusChips = computed<FilterChoice[]>(() =>
    statusOrder.map((value) => ({
        value,
        label: `${statusLabels[value]} ${props.counts[value]}`,
    })),
);

const departmentChoices = computed<FilterChoice[]>(() =>
    props.departments.map(({ id, name }) => ({
        value: String(id),
        label: name,
    })),
);

const managerChoices = computed<FilterChoice[]>(() =>
    props.managers.map(({ id, name }) => ({ value: String(id), label: name })),
);

const filters = useListFilters(index.url, {
    search: props.search,
    status: props.status ?? 'all',
    department: props.department === null ? 'all' : String(props.department),
    manager: props.manager === null ? 'all' : String(props.manager),
});

const isFiltered = computed(
    () =>
        props.search !== '' ||
        props.status !== null ||
        props.department !== null ||
        props.manager !== null,
);

function clearFilters(): void {
    Object.assign(filters, {
        search: '',
        status: 'all',
        department: 'all',
        manager: 'all',
    });
}

/** On a phone the row stacks, so the Department joins the Job Title line (P4). */
const isPhone = useMediaQuery('(max-width: 639px)');

function detailOf(row: EmployeeListEntry): string | null {
    if (!isPhone.value) {
        return row.job_title;
    }

    return [row.job_title, row.department].filter(Boolean).join(' · ') || null;
}

function open(row: EmployeeListEntry): void {
    router.visit(show(row.id));
}
</script>

<template>
    <Head title="Employees" />

    <PageContainer width="full">
        <PageHeader
            title="Employees"
            description="Everyone whose record you may see: Future, Current and Former."
        >
            <template v-if="canAdd" #actions>
                <Button as-child>
                    <Link :href="create()"><Plus />Add Employee</Link>
                </Button>
            </template>
        </PageHeader>

        <div class="flex flex-col gap-3">
            <FilterBar>
                <SegmentedControl
                    v-model="filters.status"
                    :options="statusChips"
                    label="Status"
                    size="sm"
                />
                <FilterSearch v-model="filters.search" />
                <FilterSelect
                    v-model="filters.department"
                    label="Department"
                    any="Any Department"
                    :choices="departmentChoices"
                />
                <FilterSelect
                    v-model="filters.manager"
                    label="Manager"
                    any="Any Manager"
                    :choices="managerChoices"
                />
                <template #summary>
                    <span class="font-medium text-foreground tabular-nums">{{
                        employees.total
                    }}</span>
                    {{ employees.total === 1 ? 'Employee' : 'Employees' }}
                </template>
            </FilterBar>

            <DataTable
                :columns="columns"
                :rows="employees.data"
                :on-row-click="open"
            >
                <template #cell-name="{ row }">
                    <PersonCell
                        :name="row.name"
                        :detail="detailOf(row)"
                        :href="show(row.id)"
                    />
                </template>
                <template #cell-department="{ row }">
                    <ValueOr :value="row.department" />
                </template>
                <template #cell-manager="{ row }">
                    <PersonCell v-if="row.manager" :name="row.manager.name" />
                    <span v-else class="text-muted-foreground">None</span>
                </template>
                <template #cell-start_date="{ row }">
                    <span class="whitespace-nowrap tabular-nums">{{
                        formatDate(row.start_date)
                    }}</span>
                </template>
                <template #cell-status="{ row }">
                    <EmploymentStatusBadge
                        :status="row.status"
                        :short="isPhone"
                    />
                </template>
                <template #cell-open>
                    <ChevronRight
                        class="size-4 text-muted-foreground opacity-0 group-hover/row:opacity-100"
                        aria-hidden="true"
                    />
                </template>
                <template #empty>
                    <EmptyState
                        v-if="isFiltered"
                        :icon="UsersRound"
                        message="No Employees match these filters."
                    >
                        <template #action>
                            <Button variant="secondary" @click="clearFilters"
                                >Clear filters</Button
                            >
                        </template>
                    </EmptyState>
                    <EmptyState
                        v-else
                        :icon="UsersRound"
                        message="Employees whose record you may see appear here. There are none yet."
                    >
                        <template v-if="canAdd" #action>
                            <Button as-child>
                                <Link :href="create()">Add Employee</Link>
                            </Button>
                        </template>
                    </EmptyState>
                </template>
            </DataTable>

            <Pagination :page="employees" preserve-state />
        </div>
    </PageContainer>
</template>
