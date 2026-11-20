<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import TimeOffTab from '@/components/approvals/TimeOffTab.vue';
import TravelExpensesTab from '@/components/approvals/TravelExpensesTab.vue';
import TravelRequestsTab from '@/components/approvals/TravelRequestsTab.vue';
import WorkEntriesTab from '@/components/approvals/WorkEntriesTab.vue';
import PageHeader from '@/components/PageHeader.vue';
import PageContainer from '@/components/PageContainer.vue';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { index } from '@/routes/approvals';
import type {
    ApprovalEntry,
    ApprovalFilters,
    ApprovalWeek,
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

/** Only the open tab's props are sent. */
const props = defineProps<{
    tab: ApprovalsTab;
    waiting: ApprovalsWaiting;
    filters?: ApprovalFilters;
    entries?: Paginated<ApprovalEntry>;
    total?: number;
    weeks?: ApprovalWeek[];
    /** Only sent when asked for. */
    matchingIds?: number[];
    employees?: ChoiceToApprove[];
    projects?: ChoiceToApprove[];
    toApprove?: TimeOffToApprove[];
    travelRequestsToApprove?: TravelRequestToApprove[];
    travelExpensesToApprove?: TravelExpensesToApprove[];
    currency?: string;
}>();

const description = computed(() => {
    const count =
        props.waiting.workEntries +
        props.waiting.timeOff +
        props.waiting.travelRequests +
        props.waiting.travelExpenses;

    return count === 0
        ? 'Nothing is waiting for your decision.'
        : `${count} ${count === 1 ? 'item is' : 'items are'} waiting for your decision.`;
});

function openTab(tab: string | number): void {
    router.get(index.url({ query: { tab: String(tab) } }));
}
</script>

<template>
    <Head title="Approvals" />

    <PageContainer width="full">
        <PageHeader title="Approvals" :description="description" />

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
            <TabsContent value="work-entries" class="mt-5">
                <WorkEntriesTab
                    v-if="tab === 'work-entries' && entries"
                    :filters="filters!"
                    :entries="entries"
                    :total="total!"
                    :weeks="weeks!"
                    :waiting="waiting.workEntries"
                    :matching-ids="matchingIds"
                    :employees="employees!"
                    :projects="projects!"
                />
            </TabsContent>
            <TabsContent value="time-off" class="mt-5">
                <TimeOffTab
                    v-if="tab === 'time-off' && toApprove"
                    :to-approve="toApprove"
                />
            </TabsContent>
            <TabsContent value="travel-requests" class="mt-5">
                <TravelRequestsTab
                    v-if="tab === 'travel-requests' && travelRequestsToApprove"
                    :to-approve="travelRequestsToApprove"
                />
            </TabsContent>
            <TabsContent value="travel-expenses" class="mt-5">
                <TravelExpensesTab
                    v-if="tab === 'travel-expenses' && travelExpensesToApprove"
                    :to-approve="travelExpensesToApprove"
                    :currency="currency ?? 'EUR'"
                />
            </TabsContent>
        </Tabs>
    </PageContainer>
</template>
