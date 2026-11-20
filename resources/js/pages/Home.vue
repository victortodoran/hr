<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Clock } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import HomeWidget from '@/components/home/HomeWidget.vue';
import HoursThisMonthWidget from '@/components/home/HoursThisMonthWidget.vue';
import HoursThisYearWidget from '@/components/home/HoursThisYearWidget.vue';
import NeedsAttentionWidget from '@/components/home/NeedsAttentionWidget.vue';
import TimeOffWidget from '@/components/home/TimeOffWidget.vue';
import WaitingForYouWidget from '@/components/home/WaitingForYouWidget.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import { landing } from '@/routes';
import type {
    ApprovalsWaiting,
    HoursThisMonth,
    HoursThisYear,
    NeedsAttention,
    PaidDaysLeftInYear,
    TimeOffDetails,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Home', href: landing() }],
    },
});

/**
 * Home on the dashboard template (rule L15): a widget per block, in
 * priority order. Each block is null when it doesn't apply to the Employee.
 */
defineProps<{
    /** Whether the Employee has no Manager, so records no Work Entries; only true when they can see Work Entries. */
    hasNoManager: boolean;
    /** Null for an Employee with no Manager and anyone who can't see Work Entries. */
    hoursThisMonth: HoursThisMonth | null;
    /** Null exactly when hoursThisMonth is. */
    hoursThisYear: HoursThisYear | null;
    /** This year's Paid Days left and, from October, next year's. */
    paidDaysLeft: PaidDaysLeftInYear[];
    /** The next Pending or Approved Time Off that hasn't ended. */
    nextTimeOff: TimeOffDetails | null;
    /** What of the Employee's was declined; null when nothing is. */
    needsAttention: NeedsAttention | null;
    /** What waits for the Employee to decide; null when nothing does. */
    waitingForYou: ApprovalsWaiting | null;
}>();
</script>

<template>
    <Head title="Home" />

    <PageContainer width="full">
        <PageHeader
            title="Home"
            description="What's waiting for you, and your own hours and Time Off"
        />

        <div
            class="grid grid-cols-1 items-start gap-5 md:grid-cols-2 xl:grid-cols-3"
        >
            <WaitingForYouWidget
                v-if="waitingForYou !== null"
                :waiting-for-you="waitingForYou"
            />

            <NeedsAttentionWidget
                v-if="needsAttention !== null"
                :needs-attention="needsAttention"
                class="xl:col-span-2"
            />

            <HoursThisMonthWidget
                v-if="hoursThisMonth !== null"
                :hours-this-month="hoursThisMonth"
            />

            <HomeWidget v-if="hasNoManager" title="Work">
                <EmptyState
                    :icon="Clock"
                    message="You have no Manager, so you don't record Work Entries."
                />
            </HomeWidget>

            <TimeOffWidget
                :paid-days-left="paidDaysLeft"
                :next-time-off="nextTimeOff"
            />

            <HoursThisYearWidget
                v-if="hoursThisYear !== null"
                :hours-this-year="hoursThisYear"
            />
        </div>
    </PageContainer>
</template>
