<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Clock } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import PageContainer from '@/components/PageContainer.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import TimeOffSummary from '@/components/time-off/TimeOffSummary.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { landing } from '@/routes';
import { index as approvals } from '@/routes/approvals';
import { index as timeOff } from '@/routes/time-off';
import { index as workEntries } from '@/routes/work-entries';
import type {
    ApprovalsWaiting,
    HoursThisWeek,
    PaidDaysLeft,
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
defineProps<{
    /** Null for the top Employee and anyone who can't see Work Entries. */
    hoursThisWeek: HoursThisWeek | null;
    paidDaysLeft: PaidDaysLeft;
    /** The next Pending or Approved Time Off that hasn't ended. */
    nextTimeOff: TimeOffDetails | null;
    /** Declined Time Off still to come; null when there is none. */
    declinedAhead: TimeOffDetails[] | null;
    /** What waits for the Employee to decide; null when nothing does. */
    waitingForYou: ApprovalsWaiting | null;
}>();

/**
 * Hours as people read them, such as "7.5 h".
 */
function formatHours(hours: number): string {
    return `${hours.toLocaleString(undefined, { maximumFractionDigits: 2 })} h`;
}
</script>

<template>
    <Head title="Home" />

    <PageContainer width="wide">
        <Heading
            title="Home"
            description="Your week and Time Off at a glance"
        />

        <div class="grid gap-4 sm:grid-cols-2">
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
                        variant="outline"
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
                        variant="outline"
                        as-child
                    >
                        <Link :href="approvals({ query: { tab: 'time-off' } })"
                            >{{ waitingForYou.timeOff }} Pending Time Off</Link
                        >
                    </Button>
                </CardContent>
            </Card>

            <Card v-if="hoursThisWeek !== null">
                <CardHeader>
                    <CardTitle>Hours this week</CardTitle>
                </CardHeader>
                <CardContent>
                    <EmptyState
                        v-if="
                            hoursThisWeek.hours === 0 &&
                            hoursThisWeek.pending === 0
                        "
                        :icon="Clock"
                        message="You haven't logged any hours this week."
                    >
                        <template #action>
                            <Button variant="outline" as-child>
                                <Link :href="workEntries()"
                                    >Open My Work Entries</Link
                                >
                            </Button>
                        </template>
                    </EmptyState>
                    <div v-else class="space-y-2 text-sm">
                        <p class="text-2xl font-semibold tabular-nums">
                            {{ formatHours(hoursThisWeek.hours) }}
                        </p>
                        <p
                            v-if="hoursThisWeek.pending > 0"
                            class="flex items-center gap-2"
                        >
                            <StatusBadge state="pending" />
                            <span class="text-muted-foreground"
                                >{{ hoursThisWeek.pending }} Work
                                {{
                                    hoursThisWeek.pending === 1
                                        ? 'Entry'
                                        : 'Entries'
                                }}</span
                            >
                        </p>
                        <Link
                            :href="workEntries()"
                            class="inline-block underline underline-offset-4"
                            >Open My Work Entries</Link
                        >
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Time Off</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4 text-sm">
                    <p>
                        <span
                            class="text-2xl font-semibold tabular-nums"
                            :class="{
                                'text-destructive': paidDaysLeft.days < 0,
                            }"
                            >{{ paidDaysLeft.days }}</span
                        >
                        <span class="ml-2 text-muted-foreground"
                            >Paid Days left in {{ paidDaysLeft.year }}</span
                        >
                    </p>
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
                        variant="outline"
                        as-child
                    >
                        <Link :href="timeOff()">Request Time Off</Link>
                    </Button>
                    <Link
                        v-else
                        :href="timeOff()"
                        class="inline-block underline underline-offset-4"
                        >Open Time Off</Link
                    >
                </CardContent>
            </Card>

            <Card v-if="declinedAhead !== null" class="sm:col-span-2">
                <CardHeader>
                    <CardTitle>Declined ahead</CardTitle>
                    <CardDescription
                        >Time Off still to come that your Manager
                        declined</CardDescription
                    >
                </CardHeader>
                <CardContent class="space-y-3 text-sm">
                    <div
                        v-for="declined in declinedAhead"
                        :key="declined.id"
                        class="space-y-1"
                    >
                        <StatusBadge state="declined" />
                        <TimeOffSummary :time-off="declined" />
                    </div>
                </CardContent>
            </Card>
        </div>
    </PageContainer>
</template>
