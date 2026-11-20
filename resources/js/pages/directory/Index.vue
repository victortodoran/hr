<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Network, Users } from '@lucide/vue';
import { computed } from 'vue';
import DataTable from '@/components/DataTable.vue';
import ReportingChain from '@/components/employees/ReportingChain.vue';
import EmptyState from '@/components/EmptyState.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterSearch from '@/components/FilterSearch.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import PersonCell from '@/components/PersonCell.vue';
import RecordSheet from '@/components/RecordSheet.vue';
import RowActions from '@/components/RowActions.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useListFilters } from '@/composables/useListFilters';
import { index } from '@/routes/directory';
import { show as employeePage } from '@/routes/employees';
import type {
    DirectReport,
    DirectoryEntry,
    DirectoryTab,
    Paginated,
    ReportingChainView,
    TableColumn,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Directory', href: index() }],
    },
});

/**
 * Only the open tab's props are sent: `employees` and `search` on the
 * Everyone tab, `directReports` on the Reports tab.
 */
const props = defineProps<{
    tab: DirectoryTab;
    /** The tabs the viewer may open; the bar shows only when there are two. */
    tabs: DirectoryTab[];
    employees?: Paginated<DirectoryEntry>;
    search?: string;
    directReports?: DirectReport[];
    /** The Employee open in the sheet (`?person=`), with their Reporting Chain. */
    person: ReportingChainView | null;
}>();

const tabLabels: Record<DirectoryTab, string> = {
    everyone: 'Everyone',
    reports: 'Reports',
};

const everyoneColumns: TableColumn[] = [
    { key: 'name', label: 'Name', phone: 'primary' },
    { key: 'email', label: 'Email', phone: 'secondary' },
    { key: 'department', label: 'Department', minor: true },
    { key: 'manager', label: 'Manager', minor: true },
    { key: 'actions', label: '', align: 'end', phone: 'end' },
];

const reportsColumns: TableColumn[] = [
    { key: 'name', label: 'Name', phone: 'primary' },
    { key: 'email', label: 'Email', phone: 'secondary' },
    { key: 'department', label: 'Department', minor: true },
];

const emptyMessage = computed(
    () =>
        `Everyone currently working at the company appears here. ${
            props.search
                ? `Nobody matches “${props.search}”.`
                : 'Nobody is working here yet.'
        }`,
);

const filters = useListFilters(index.url, { search: props.search ?? '' });

function openTab(tab: string | number): void {
    router.get(index.url({ query: { tab: String(tab) } }));
}

/**
 * Opens an Employee's sheet over the list (rule K8), loading only their
 * Reporting Chain and keeping the tab, search and page in the URL.
 */
function openPerson(id: number): void {
    router.get(
        index.url({ mergeQuery: { person: id } }),
        {},
        { only: ['person'], preserveState: true, preserveScroll: true },
    );
}

/** Closing the sheet drops `?person=` from the URL without a visit. */
const personOpen = computed({
    get: () => props.person !== null,
    set: (open: boolean) => {
        if (!open) {
            router.replace({
                url: index.url({ mergeQuery: { person: null } }),
                props: (current) => ({ ...current, person: null }),
                preserveState: true,
                preserveScroll: true,
            });
        }
    },
});

/** "Developer · Engineering", the line under the sheet's title. */
const personDetail = computed(() =>
    [props.person?.employee.job_title, props.person?.employee.department]
        .filter(Boolean)
        .join(' · '),
);
</script>

<template>
    <Head title="Directory" />

    <PageContainer width="full">
        <PageHeader
            title="Directory"
            :description="
                tab === 'reports'
                    ? 'The Employees who report to you.'
                    : 'Find anyone currently working at the company.'
            "
        />

        <Tabs class="gap-5" :model-value="tab" @update:model-value="openTab">
            <TabsList v-if="tabs.length > 1" class="self-start">
                <TabsTrigger v-for="name in tabs" :key="name" :value="name">
                    {{ tabLabels[name] }}
                </TabsTrigger>
            </TabsList>

            <TabsContent value="everyone" class="flex flex-col gap-5">
                <template v-if="tab === 'everyone' && employees">
                    <FilterBar>
                        <FilterSearch v-model="filters.search" />
                    </FilterBar>

                    <DataTable
                        :columns="everyoneColumns"
                        :rows="employees.data"
                        :on-row-click="(row) => openPerson(row.id)"
                    >
                        <template #cell-name="{ row }">
                            <PersonCell
                                :name="row.name"
                                :detail="row.job_title"
                            />
                        </template>
                        <template #cell-email="{ row }">
                            <a
                                :href="`mailto:${row.email}`"
                                class="break-all underline-offset-4 hover:underline"
                                >{{ row.email }}</a
                            >
                        </template>
                        <template #cell-manager="{ row }">
                            <PersonCell
                                v-if="row.manager"
                                :name="row.manager.name"
                            />
                            <span v-else class="text-muted-foreground"
                                >None</span
                            >
                        </template>
                        <template #cell-actions="{ row }">
                            <RowActions :label="row.name">
                                <template #menu>
                                    <DropdownMenuItem
                                        @select="openPerson(row.id)"
                                        >Open</DropdownMenuItem
                                    >
                                </template>
                            </RowActions>
                        </template>
                        <template #empty>
                            <EmptyState :icon="Users" :message="emptyMessage" />
                        </template>
                    </DataTable>

                    <Pagination :page="employees" />
                </template>
            </TabsContent>

            <TabsContent value="reports">
                <DataTable
                    v-if="tab === 'reports' && directReports"
                    :columns="reportsColumns"
                    :rows="directReports"
                >
                    <template #cell-name="{ row }">
                        <PersonCell
                            :name="row.name"
                            :detail="row.job_title"
                            :href="
                                row.can_view ? employeePage(row.id) : undefined
                            "
                        />
                    </template>
                    <template #cell-email="{ row }">
                        <a
                            :href="`mailto:${row.email}`"
                            class="break-all underline-offset-4 hover:underline"
                            >{{ row.email }}</a
                        >
                    </template>
                    <template #empty>
                        <EmptyState
                            :icon="Network"
                            message="Your Direct Reports appear here. Nobody reports to you right now."
                        />
                    </template>
                </DataTable>
            </TabsContent>
        </Tabs>

        <RecordSheet
            v-if="person"
            v-model:open="personOpen"
            :title="person.employee.name"
            :description="personDetail || undefined"
        >
            <section class="flex flex-col gap-3">
                <h3 class="text-section">Reporting Chain</h3>
                <p class="text-meta text-muted-foreground">
                    Who they report to, up to the top of the company, and whom
                    they are the Manager of.
                </p>
                <ReportingChain :chain="person" />
            </section>
            <template v-if="person.employee.link === 'employee'" #footer>
                <Button variant="secondary" as-child>
                    <Link :href="employeePage(person.employee.id)"
                        >Open Employee page</Link
                    >
                </Button>
            </template>
        </RecordSheet>
    </PageContainer>
</template>
