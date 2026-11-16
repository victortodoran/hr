<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import TimeOffTab from '@/components/approvals/TimeOffTab.vue';
import TravelRequestsTab from '@/components/approvals/TravelRequestsTab.vue';
import WorkEntriesTab from '@/components/approvals/WorkEntriesTab.vue';
import Heading from '@/components/Heading.vue';
import PageContainer from '@/components/PageContainer.vue';
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
    TravelRequestToApprove,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Approvals', href: index() }],
    },
});

/**
 * Only the open tab's props are sent: the Work Entries ones on the Work
 * Entries tab, `toApprove` on the Time Off tab and `travelRequestsToApprove`
 * on the Travel Requests tab.
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
            description="Pending Work Entries, Time Off and Travel Requests waiting for your decision"
        />

        <Tabs :model-value="tab" @update:model-value="openTab">
            <TabsList class="self-start">
                <TabsTrigger value="work-entries"
                    >Work Entries
                    <span class="text-muted-foreground tabular-nums">{{
                        waiting.workEntries
                    }}</span></TabsTrigger
                >
                <TabsTrigger value="time-off"
                    >Time Off
                    <span class="text-muted-foreground tabular-nums">{{
                        waiting.timeOff
                    }}</span></TabsTrigger
                >
                <TabsTrigger value="travel-requests"
                    >Travel Requests
                    <span class="text-muted-foreground tabular-nums">{{
                        waiting.travelRequests
                    }}</span></TabsTrigger
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
        </Tabs>
    </PageContainer>
</template>
