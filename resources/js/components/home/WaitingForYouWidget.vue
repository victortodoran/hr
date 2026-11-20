<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { CalendarDays, ChevronRight, Clock, Plane, Receipt } from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';
import HomeWidget from '@/components/home/HomeWidget.vue';
import WidgetRows from '@/components/home/WidgetRows.vue';
import { index as approvals } from '@/routes/approvals';
import type { ApprovalsTab, ApprovalsWaiting } from '@/types';

/**
 * What waits for the signed-in Employee to decide: a row per kind with any
 * Pending, its count and a chevron, each opening its Approvals tab.
 */
const props = defineProps<{
    waitingForYou: ApprovalsWaiting;
}>();

type Counter = {
    tab: ApprovalsTab;
    label: string;
    icon: Component;
    count: number;
    href: NonNullable<InertiaLinkProps['href']>;
};

const counters = computed<Counter[]>(() =>
    (
        [
            {
                tab: 'work-entries',
                label: 'Work Entries',
                icon: Clock,
                count: props.waitingForYou.workEntries,
            },
            {
                tab: 'time-off',
                label: 'Time Off',
                icon: CalendarDays,
                count: props.waitingForYou.timeOff,
            },
            {
                tab: 'travel-requests',
                label: 'Travel Requests',
                icon: Plane,
                count: props.waitingForYou.travelRequests,
            },
            {
                tab: 'travel-expenses',
                label: 'Travel Expenses',
                icon: Receipt,
                count: props.waitingForYou.travelExpenses,
            },
        ] satisfies Omit<Counter, 'href'>[]
    )
        .filter((counter) => counter.count > 0)
        .map((counter) => ({
            ...counter,
            href: approvals({ query: { tab: counter.tab } }),
        })),
);
</script>

<template>
    <HomeWidget
        title="Waiting for you"
        description="Pending requests from the Employees you decide for"
        :href="approvals()"
        link-label="Open Approvals"
        data-test="waiting-for-you"
    >
        <WidgetRows :rows="counters" :row-key="(counter) => counter.tab">
            <template #default="{ row: counter }">
                <Link
                    :href="counter.href"
                    class="flex min-h-11 items-center gap-3 rounded-md py-2 text-body hover:bg-surface focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <component
                        :is="counter.icon"
                        class="size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <span class="flex-1 font-medium">{{ counter.label }}</span>
                    <span class="font-semibold tabular-nums">{{
                        counter.count
                    }}</span>
                    <ChevronRight
                        class="size-4 shrink-0 text-subtle-foreground"
                        aria-hidden="true"
                    />
                </Link>
            </template>
        </WidgetRows>
    </HomeWidget>
</template>
