<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Network } from '@lucide/vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import PageContainer from '@/components/PageContainer.vue';
import ValueOr from '@/components/ValueOr.vue';
import { show } from '@/routes/employees';
import { index } from '@/routes/team';
import type { DirectReport, TableColumn } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Team', href: index() }],
    },
});

defineProps<{
    directReports: DirectReport[];
}>();

const columns: TableColumn[] = [
    { key: 'name', label: 'Name' },
    { key: 'email', label: 'Email' },
    { key: 'job_title', label: 'Job Title', minor: true },
    { key: 'department', label: 'Department', minor: true },
];
</script>

<template>
    <Head title="Team" />

    <PageContainer width="wide">
        <Heading
            title="Team"
            description="The Employees you are the Manager of"
        />

        <DataTable :columns="columns" :rows="directReports">
            <template #cell-name="{ row }">
                <Link
                    v-if="row.can_view"
                    :href="show(row.id)"
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
    </PageContainer>
</template>
