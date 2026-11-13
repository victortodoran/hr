<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import TimeOffTab from '@/components/approvals/TimeOffTab.vue';
import WorkEntriesTab from '@/components/approvals/WorkEntriesTab.vue';
import Heading from '@/components/Heading.vue';
import PageContainer from '@/components/PageContainer.vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { index } from '@/routes/approvals';
import type {
    ApprovalEntry,
    ApprovalsTab,
    ApprovalsWaiting,
    Choice,
    Paginated,
    TimeOffToApprove,
    WorkEntryFilters,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Approvals', href: index() }],
    },
});

/**
 * Only the open tab's props are sent: the Work Entries ones on the Work
 * Entries tab, `toApprove` on the Time Off tab.
 */
defineProps<{
    tab: ApprovalsTab;
    waiting: ApprovalsWaiting;
    filters?: WorkEntryFilters;
    entries?: Paginated<ApprovalEntry>;
    /** Total hours of every Work Entry matching the filters. */
    total?: number;
    /** Every matching Work Entry's ID; only sent when asked for. */
    matchingIds?: number[];
    employees?: Choice[];
    projects?: Choice[];
    /** Direct Reports' Pending Time Off. */
    toApprove?: TimeOffToApprove[];
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
            description="Pending Work Entries and Time Off waiting for your decision"
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
        </Tabs>
    </PageContainer>
</template>
