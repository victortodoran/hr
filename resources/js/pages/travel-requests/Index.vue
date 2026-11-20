<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref, watch } from 'vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import ScopeSwitch from '@/components/ScopeSwitch.vue';
import RequestTravelSheet from '@/components/travel-requests/RequestTravelSheet.vue';
import TravelRequestList from '@/components/travel-requests/TravelRequestList.vue';
import { Button } from '@/components/ui/button';
import { create, index } from '@/routes/travel-requests';
import type { TravelAssignment, TravelHistory } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Travel', href: index() }],
    },
});

/**
 * The Employee's own travel (rule L11): one table of their Travel
 * Requests, Upcoming first. Request Travel, the one primary (B2), opens
 * its sheet over the page; the sheet's own URL, `travel-requests/create`,
 * renders this page with `requesting` set (L14).
 */
const props = defineProps<{
    travelRequests: TravelHistory;
    /** Whether the Request Travel sheet is open. */
    requesting?: boolean;
    /** The earliest first date, sent with the sheet. */
    today?: string;
    /** The Assignments the Employee can still travel on, sent with the sheet. */
    assignments?: TravelAssignment[];
}>();

const sheetOpen = ref(props.requesting === true);

watch(
    () => props.requesting,
    (requesting) => {
        sheetOpen.value = requesting === true;
    },
);

/** Closing the sheet returns to the page's own URL. */
function setSheetOpen(open: boolean): void {
    sheetOpen.value = open;

    if (!open && props.requesting) {
        router.get(
            index().url,
            {},
            { preserveScroll: true, preserveState: true },
        );
    }
}
</script>

<template>
    <Head title="Travel" />

    <PageContainer width="full">
        <PageHeader
            title="Travel"
            description="Your travel for work and the Travel Expenses you paid for it."
        >
            <template #actions>
                <Button as-child>
                    <Link :href="create()" preserve-scroll preserve-state
                        ><Plus />Request Travel</Link
                    >
                </Button>
            </template>
        </PageHeader>
        <ScopeSwitch area="travel" />

        <TravelRequestList :travel-requests="travelRequests" />

        <RequestTravelSheet
            :open="sheetOpen"
            :today="today ?? ''"
            :assignments="assignments ?? []"
            @update:open="setSheetOpen"
        />
    </PageContainer>
</template>
