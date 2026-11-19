<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Target } from '@lucide/vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageContainer from '@/components/PageContainer.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useInitials } from '@/composables/useInitials';
import { outcomes } from '@/lib/outcomes';
import { create, index, show } from '@/routes/performance-evaluations';
import type { Paginated, PerformanceEvaluationRow, TableColumn } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Performance Evaluations', href: index() }],
    },
});

/**
 * For one year, the Employees the viewer effectively manages or may read
 * the Performance Evaluations of who have one for it, each with that
 * Performance Evaluation, and a way to create one when the viewer may.
 */
const props = defineProps<{
    year: number;
    years: number[];
    canCreate: boolean;
    employees: Paginated<PerformanceEvaluationRow>;
}>();

const { getInitials } = useInitials();

const columns: TableColumn[] = [
    { key: 'name', label: 'Employee' },
    { key: 'goals', label: 'Goals', minor: true },
    { key: 'state', label: 'Status', align: 'end' },
];

function showYear(year: unknown): void {
    router.get(
        index.url({ query: { year: String(year) } }),
        {},
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head title="Performance Evaluations" />

    <PageContainer width="wide">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-semibold tracking-tight">
                    Performance Evaluations
                </h1>
                <p class="text-sm text-muted-foreground">
                    The Evaluations you write for the Employees you are the
                    Manager of, and those you may read
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Select
                    :model-value="String(props.year)"
                    @update:model-value="showYear"
                >
                    <SelectTrigger class="w-32" aria-label="Year">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in years"
                            :key="option"
                            :value="String(option)"
                            >{{ option }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <Button v-if="canCreate" as-child>
                    <Link :href="create()">
                        <Plus class="size-4" aria-hidden="true" />
                        New Evaluation
                    </Link>
                </Button>
            </div>
        </header>

        <!--
            DataTable's own classes are overridden from here for this page's
            look: a rounder box, roomier rows, and rows that are positioned so
            the name's link can stretch over the whole row and hover it.
        -->
        <DataTable
            :columns="columns"
            :rows="employees.data"
            class="[&]:rounded-xl [&_tbody_td]:py-3.5 [&_tbody_td]:align-middle [&_tbody_td]:whitespace-nowrap [&_tbody_tr]:relative [&_tbody_tr:has(a:hover)]:bg-muted/50"
        >
            <template #cell-name="{ row }">
                <div class="flex items-center gap-3">
                    <Avatar class="size-9" aria-hidden="true">
                        <AvatarFallback class="text-xs font-medium">{{
                            getInitials(row.name)
                        }}</AvatarFallback>
                    </Avatar>
                    <Link
                        :href="show(row.evaluation.id)"
                        class="font-medium after:absolute after:inset-0"
                        >{{ row.name }}</Link
                    >
                </div>
            </template>
            <template #cell-goals="{ row }">
                {{ row.evaluation.goals_count }}
            </template>
            <template #cell-state="{ row }">
                <div class="flex items-center justify-end gap-2">
                    <span
                        v-if="row.evaluation.outcome"
                        class="text-muted-foreground"
                        >{{ outcomes[row.evaluation.outcome] }}</span
                    >
                    <StatusBadge :state="row.evaluation.state" />
                    <StatusBadge
                        v-if="row.evaluation.at_risk_goals_count > 0"
                        state="at-risk"
                        >{{ row.evaluation.at_risk_goals_count }} At
                        Risk</StatusBadge
                    >
                </div>
            </template>
            <template #empty>
                <EmptyState
                    :icon="Target"
                    :message="`The ${year} Performance Evaluations you write or may read appear here.`"
                >
                    <template v-if="canCreate" #action>
                        <Button variant="outline" as-child>
                            <Link :href="create()">New Evaluation</Link>
                        </Button>
                    </template>
                </EmptyState>
            </template>
        </DataTable>

        <Pagination :page="employees" preserve-state />
    </PageContainer>
</template>
