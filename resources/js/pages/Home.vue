<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Clock } from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import NeedsAttentionCard from '@/components/home/NeedsAttentionCard.vue';
import PageContainer from '@/components/PageContainer.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import PaidDaysLeft from '@/components/time-off/PaidDaysLeft.vue';
import TimeOffSummary from '@/components/time-off/TimeOffSummary.vue';
import HoursThisYearCard from '@/components/work-entries/HoursThisYearCard.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatHours } from '@/lib/work-entries';
import { landing } from '@/routes';
import { index as approvals } from '@/routes/approvals';
import { create as requestTimeOff, index as timeOff } from '@/routes/time-off';
import { index as workEntries } from '@/routes/work-entries';
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
 * Each block is null when it doesn't apply to the Employee.
 */
const props = defineProps<{
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

/** The Work page on this month. */
const thisMonthsWorkEntries = computed(() =>
    workEntries({ query: { month: props.hoursThisMonth?.month ?? '' } }),
);
</script>

<template>
    <Head title="Home" />

    <PageContainer width="full">
        <PageHeader
            title="Home"
            description="Your hours and Time Off at a glance"
        />

        <div class="grid gap-4 sm:grid-cols-2">
            <NeedsAttentionCard
                v-if="needsAttention !== null"
                :needs-attention="needsAttention"
                class="sm:col-span-2"
            />

            <Card v-if="waitingForYou !== null" class="sm:col-span-2">
                <CardHeader>
                    <CardTitle>Waiting for you</CardTitle>
                    <CardDescription
                        >Pending requests from the Employees you decide
                        for</CardDescription
                    >
                </CardHeader>
                <CardContent class="flex flex-wrap gap-3">
                    <Button
                        v-if="waitingForYou.workEntries > 0"
                        variant="secondary"
                        as-child
                    >
                        <Link
                            :href="
                                approvals({ query: { tab: 'work-entries' } })
                            "
                            >{{ waitingForYou.workEntries }} Pending Work
                            {{
                                waitingForYou.workEntries === 1
                                    ? 'Entry'
                                    : 'Entries'
                            }}</Link
                        >
                    </Button>
                    <Button
                        v-if="waitingForYou.timeOff > 0"
                        variant="secondary"
                        as-child
                    >
                        <Link :href="approvals({ query: { tab: 'time-off' } })"
                            >{{ waitingForYou.timeOff }} Pending Time Off</Link
                        >
                    </Button>
                    <Button
                        v-if="waitingForYou.travelRequests > 0"
                        variant="secondary"
                        as-child
                    >
                        <Link
                            :href="
                                approvals({ query: { tab: 'travel-requests' } })
                            "
                            >{{ waitingForYou.travelRequests }} Pending Travel
                            {{
                                waitingForYou.travelRequests === 1
                                    ? 'Request'
                                    : 'Requests'
                            }}</Link
                        >
                    </Button>
                    <Button
                        v-if="waitingForYou.travelExpenses > 0"
                        variant="secondary"
                        as-child
                    >
                        <Link
                            :href="
                                approvals({ query: { tab: 'travel-expenses' } })
                            "
                            >{{ waitingForYou.travelExpenses }} Pending Travel
                            {{
                                waitingForYou.travelExpenses === 1
                                    ? 'Expense'
                                    : 'Expenses'
                            }}</Link
                        >
                    </Button>
                </CardContent>
            </Card>

            <Card v-if="hoursThisMonth !== null">
                <CardHeader>
                    <CardTitle>Hours this month</CardTitle>
                </CardHeader>
                <CardContent>
                    <EmptyState
                        v-if="hoursThisMonth.hours === 0"
                        :icon="Clock"
                        message="No hours this month yet. Declined ones don't count."
                    >
                        <template #action>
                            <Button variant="secondary" as-child>
                                <Link :href="thisMonthsWorkEntries"
                                    >Open Work</Link
                                >
                            </Button>
                        </template>
                    </EmptyState>
                    <div v-else class="space-y-2 text-sm">
                        <p class="text-2xl font-semibold tabular-nums">
                            {{ formatHours(hoursThisMonth.hours) }}
                        </p>
                        <p
                            v-if="hoursThisMonth.pendingHours > 0"
                            class="flex items-center gap-2"
                        >
                            <StatusBadge state="pending" />
                            <span class="text-muted-foreground">{{
                                formatHours(hoursThisMonth.pendingHours)
                            }}</span>
                        </p>
                        <Link
                            :href="thisMonthsWorkEntries"
                            class="inline-block underline underline-offset-4"
                            >Open Work</Link
                        >
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Time Off</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4 text-sm">
                    <PaidDaysLeft :paid-days-left="paidDaysLeft" />
                    <div class="space-y-2">
                        <p class="font-medium">Next Time Off</p>
                        <p
                            v-if="nextTimeOff === null"
                            class="text-muted-foreground"
                        >
                            You have no upcoming Time Off.
                        </p>
                        <template v-else>
                            <StatusBadge :state="nextTimeOff.state" />
                            <TimeOffSummary :time-off="nextTimeOff" />
                        </template>
                    </div>
                    <Button
                        v-if="nextTimeOff === null"
                        variant="secondary"
                        as-child
                    >
                        <Link :href="requestTimeOff()">Request Time Off</Link>
                    </Button>
                    <Link
                        v-else
                        :href="timeOff()"
                        class="inline-block underline underline-offset-4"
                        >Open Time Off</Link
                    >
                </CardContent>
            </Card>

            <HoursThisYearCard
                v-if="hoursThisYear !== null"
                :hours-this-year="hoursThisYear"
                class="sm:col-span-2"
            />
        </div>
    </PageContainer>
</template>
