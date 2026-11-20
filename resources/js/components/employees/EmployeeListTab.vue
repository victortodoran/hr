<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import EmployeePageSection from '@/components/employees/EmployeePageSection.vue';
import Pagination from '@/components/Pagination.vue';
import PeriodNavigation from '@/components/PeriodNavigation.vue';
import StatStrip from '@/components/StatStrip.vue';
import { Button } from '@/components/ui/button';
import { show } from '@/routes/employees';
import type { Choice, EmployeePageTab, Paginated, Period } from '@/types';

const props = defineProps<{
    employee: Choice;
    tab: EmployeePageTab;
    period: Period;
    today: string;
    noFuture?: boolean;
    stats: { label: string; value: string; muted?: boolean }[];
    title: string;
    listName: string;
    listHref: InertiaLinkProps['href'];
    page: Paginated<unknown>;
}>();

function choosePeriod(period: Record<string, string>): void {
    router.get(
        show.url(props.employee.id, { query: { tab: props.tab, ...period } }),
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <PeriodNavigation
            :period="period"
            :today="today"
            :no-future="noFuture"
            @change="choosePeriod"
        />

        <StatStrip v-if="page.total > 0" :stats="stats" />

        <EmployeePageSection :title="title">
            <template #action>
                <Button variant="secondary" size="sm" as-child>
                    <Link :href="listHref">Open in {{ listName }}</Link>
                </Button>
            </template>

            <div class="flex flex-col gap-3">
                <slot />

                <Pagination :page="page" />
            </div>
        </EmployeePageSection>
    </div>
</template>
