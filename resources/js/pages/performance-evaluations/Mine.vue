<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Target } from '@lucide/vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import PageContainer from '@/components/PageContainer.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { outcomes } from '@/lib/outcomes';
import { index } from '@/routes/my-performance-evaluations';
import { show } from '@/routes/performance-evaluations';
import type { OwnPerformanceEvaluation, TableColumn } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Performance', href: index() }],
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
    <Head title="Performance" />

    <PageContainer width="wide">
        <Heading
            title="Performance"
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
                <div class="flex items-center justify-end gap-2">
                    <span v-if="row.outcome" class="text-muted-foreground">{{
                        outcomes[row.outcome]
                    }}</span>
                    <StatusBadge :state="row.state" />
                </div>
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
