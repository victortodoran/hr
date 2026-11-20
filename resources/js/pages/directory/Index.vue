<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Network, Users } from '@lucide/vue';
import { computed } from 'vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterSearch from '@/components/FilterSearch.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import ValueOr from '@/components/ValueOr.vue';
import { useListFilters } from '@/composables/useListFilters';
import { index, show } from '@/routes/directory';
import { show as employeePage } from '@/routes/employees';
import type {
    DirectReport,
    DirectoryEntry,
    DirectoryTab,
    Paginated,
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
}>();

const tabLabels: Record<DirectoryTab, string> = {
    everyone: 'Everyone',
    reports: 'Reports',
};

const everyoneColumns: TableColumn[] = [
    { key: 'name', label: 'Name' },
    { key: 'email', label: 'Email' },
    { key: 'job_title', label: 'Job Title', minor: true },
    { key: 'department', label: 'Department', minor: true },
    { key: 'manager', label: 'Manager', minor: true },
];

const reportsColumns: TableColumn[] = everyoneColumns.filter(
    (column) => column.key !== 'manager',
);

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
</script>

<template>
    <Head title="Directory" />

    <PageContainer width="full">
        <PageHeader
            title="Directory"
            :description="
                tab === 'reports'
                    ? 'The Employees you are the Manager of'
                    : 'Everyone currently working at the company'
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
                    >
                        <template #cell-name="{ row }">
                            <Link
                                :href="show(row.id)"
                                class="font-medium underline-offset-4 hover:underline"
                                >{{ row.name }}</Link
                            >
                        </template>
                        <template #cell-email="{ row }">
                            <a
                                :href="`mailto:${row.email}`"
                                class="break-all underline-offset-4 hover:underline"
                                >{{ row.email }}</a
                            >
                        </template>
                        <template #cell-job_title="{ row }">
                            <ValueOr :value="row.job_title" />
                        </template>
                        <template #cell-manager="{ row }">
                            <Link
                                v-if="row.manager"
                                :href="show(row.manager.id)"
                                class="underline-offset-4 hover:underline"
                                >{{ row.manager.name }}</Link
                            >
                            <span v-else class="text-muted-foreground"
                                >None</span
                            >
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
                        <Link
                            v-if="row.can_view"
                            :href="employeePage(row.id)"
                            class="font-medium underline-offset-4 hover:underline"
                            >{{ row.name }}</Link
                        >
                        <span v-else class="font-medium">{{ row.name }}</span>
                    </template>
                    <template #cell-email="{ row }">
                        <a
                            :href="`mailto:${row.email}`"
                            class="break-all underline-offset-4 hover:underline"
                            >{{ row.email }}</a
                        >
                    </template>
                    <template #cell-job_title="{ row }">
                        <ValueOr :value="row.job_title" />
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
    </PageContainer>
</template>
