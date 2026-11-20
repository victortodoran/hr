<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import TimeOffTab from '@/components/approvals/TimeOffTab.vue';
import TravelExpensesTab from '@/components/approvals/TravelExpensesTab.vue';
import TravelRequestsTab from '@/components/approvals/TravelRequestsTab.vue';
import WorkEntriesTab from '@/components/approvals/WorkEntriesTab.vue';
import Heading from '@/components/Heading.vue';
import PageContainer from '@/components/PageContainer.vue';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { index } from '@/routes/approvals';
import type {
    ApprovalEntry,
    ApprovalFilters,
    ApprovalsTab,
    ApprovalsWaiting,
    Paginated,
    ChoiceToApprove,
    TimeOffToApprove,
    TravelExpensesToApprove,
    TravelRequestToApprove,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Approvals', href: index() }],
    },
});

/**
 * Only the open tab's props are sent: the Work Entries ones on the Work
 * Entries tab, `toApprove` on the Time Off tab, `travelRequestsToApprove`
 * on the Travel Requests tab and `travelExpensesToApprove` on the Travel
 * Expenses tab.
 */
defineProps<{
    tab: ApprovalsTab;
    waiting: ApprovalsWaiting;
    filters?: ApprovalFilters;
    entries?: Paginated<ApprovalEntry>;
    /** Total hours of every Work Entry matching the filters. */
    total?: number;
    /** Every matching Work Entry's ID; only sent when asked for. */
    matchingIds?: number[];
    employees?: ChoiceToApprove[];
    projects?: ChoiceToApprove[];
    /** Direct Reports' Pending Time Off. */
    toApprove?: TimeOffToApprove[];
    /** Pending Travel Requests of the Employees the viewer manages. */
    travelRequestsToApprove?: TravelRequestToApprove[];
    /** Pending Travel Expenses of the Employees the viewer manages, by Travel Request. */
    travelExpensesToApprove?: TravelExpensesToApprove[];
}>();

function openTab(tab: string | number): void {
    router.get(index.url({ query: { tab: String(tab) } }));
}
</script>

<template>
    <Head title="Approvals" />

    <PageContainer width="wide">
        <Heading
            title="Approvals"
            description="Pending Work Entries, Time Off, Travel Requests and Travel Expenses waiting for your decision"
        />

        <Tabs :model-value="tab" @update:model-value="openTab">
            <TabsList
                class="max-w-full justify-start self-start overflow-x-auto"
            >
                <TabsTrigger value="work-entries"
                    >Work Entries
                    <Badge variant="secondary">{{
                        waiting.workEntries
                    }}</Badge></TabsTrigger
                >
                <TabsTrigger value="time-off"
                    >Time Off
                    <Badge variant="secondary">{{
                        waiting.timeOff
                    }}</Badge></TabsTrigger
                >
                <TabsTrigger value="travel-requests"
                    >Travel Requests
                    <Badge variant="secondary">{{
                        waiting.travelRequests
                    }}</Badge></TabsTrigger
                >
                <TabsTrigger value="travel-expenses"
                    >Travel Expenses
                    <Badge variant="secondary">{{
                        waiting.travelExpenses
                    }}</Badge></TabsTrigger
                >
            </TabsList>
            <TabsContent value="work-entries" class="mt-4">
                <WorkEntriesTab
                    v-if="tab === 'work-entries' && entries"
                    :filters="filters!"
                    :entries="entries"
                    :total="total!"
                    :matching-ids="matchingIds"
                    :employees="employees!"
                    :projects="projects!"
                />
            </TabsContent>
            <TabsContent value="time-off" class="mt-4">
                <TimeOffTab
                    v-if="tab === 'time-off' && toApprove"
                    :to-approve="toApprove"
                />
            </TabsContent>
            <TabsContent value="travel-requests" class="mt-4">
                <TravelRequestsTab
                    v-if="tab === 'travel-requests' && travelRequestsToApprove"
                    :to-approve="travelRequestsToApprove"
                />
            </TabsContent>
            <TabsContent value="travel-expenses" class="mt-4">
                <TravelExpensesTab
                    v-if="tab === 'travel-expenses' && travelExpensesToApprove"
                    :to-approve="travelExpensesToApprove"
                />
            </TabsContent>
        </Tabs>
    </PageContainer>
</template>
