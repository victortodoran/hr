<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import ScopeSwitch from '@/components/ScopeSwitch.vue';
import RequestTravelSheet from '@/components/travel-requests/RequestTravelSheet.vue';
import TravelRequestList from '@/components/travel-requests/TravelRequestList.vue';
import { Button } from '@/components/ui/button';
import { useOverlayRoute } from '@/composables/useOverlayRoute';
import { create, index } from '@/routes/travel-requests';
import type { TravelAssignment, TravelHistory } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Travel', href: index() }],
    },
});

const props = defineProps<{
    travelRequests: TravelHistory;
    requesting?: boolean;
    today?: string;
    assignments?: TravelAssignment[];
}>();

const { open: sheetOpen, setOpen: setSheetOpen } = useOverlayRoute(
    () => props.requesting === true,
    () => index().url,
);
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
