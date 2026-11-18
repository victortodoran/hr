<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Target } from '@lucide/vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import PageContainer from '@/components/PageContainer.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { index } from '@/routes/my-performance-evaluations';
import { show } from '@/routes/performance-evaluations';
import type { OwnPerformanceEvaluation, TableColumn } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'My Performance Evaluations', href: index() }],
    },
});

defineProps<{
    evaluations: OwnPerformanceEvaluation[];
}>();

const columns: TableColumn[] = [
    { key: 'year', label: 'Year' },
    { key: 'goals_count', label: 'Goals' },
    { key: 'state', label: 'Status', align: 'end' },
];
</script>

<template>
    <Head title="My Performance Evaluations" />

    <PageContainer width="wide">
        <Heading
            title="My Performance Evaluations"
            description="The Goals your Manager gives you for each year"
        />

        <DataTable :columns="columns" :rows="evaluations">
            <template #cell-year="{ row }">
                <Link
                    :href="show(row.id)"
                    class="font-medium underline-offset-4 hover:underline"
                    >{{ row.year }}</Link
                >
            </template>
            <template #cell-state="{ row }">
                <StatusBadge :state="row.state" />
            </template>
            <template #empty>
                <EmptyState
                    :icon="Target"
                    message="Your Performance Evaluations appear here once your Manager starts one."
                />
            </template>
        </DataTable>
    </PageContainer>
</template>
