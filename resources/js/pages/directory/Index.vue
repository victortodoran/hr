<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Users } from '@lucide/vue';
import { computed } from 'vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterSearch from '@/components/FilterSearch.vue';
import Heading from '@/components/Heading.vue';
import PageContainer from '@/components/PageContainer.vue';
import Pagination from '@/components/Pagination.vue';
import ValueOr from '@/components/ValueOr.vue';
import { useListFilters } from '@/composables/useListFilters';
import { index, show } from '@/routes/directory';
import type { DirectoryEntry, Paginated, TableColumn } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Directory', href: index() }],
    },
});

const props = defineProps<{
    employees: Paginated<DirectoryEntry>;
    search: string;
}>();

const columns: TableColumn[] = [
    { key: 'name', label: 'Name' },
    { key: 'email', label: 'Email' },
    { key: 'job_title', label: 'Job Title', minor: true },
    { key: 'department', label: 'Department', minor: true },
    { key: 'manager', label: 'Manager', minor: true },
];

const emptyMessage = computed(
    () =>
        `Everyone currently working at the company appears here. ${
            props.search
                ? `Nobody matches “${props.search}”.`
                : 'Nobody is working here yet.'
        }`,
);

const filters = useListFilters(index.url, { search: props.search });
</script>

<template>
    <Head title="Directory" />

    <PageContainer width="wide">
        <Heading
            title="Directory"
            description="Everyone currently working at the company"
        />

        <FilterBar>
            <FilterSearch v-model="filters.search" />
        </FilterBar>

        <DataTable :columns="columns" :rows="employees.data">
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
                <span v-else class="text-muted-foreground">None</span>
            </template>
            <template #empty>
                <EmptyState :icon="Users" :message="emptyMessage" />
            </template>
        </DataTable>

        <Pagination :page="employees" />
    </PageContainer>
</template>
