<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import PageContainer from '@/components/PageContainer.vue';
import RequestTravelDialog from '@/components/travel-requests/RequestTravelDialog.vue';
import TravelRequestList from '@/components/travel-requests/TravelRequestList.vue';
import { index } from '@/routes/travel-requests';
import type {
    TravelAssignment,
    TravelCurrency,
    TravelRequestRecord,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Travel', href: index() }],
    },
});

defineProps<{
    today: string;
    currency: TravelCurrency;
    travelRequests: TravelRequestRecord[];
    assignments: TravelAssignment[];
}>();
</script>

<template>
    <Head title="Travel" />

    <PageContainer width="wide">
        <Heading
            title="Travel"
            description="Ask to travel for work on one of your Assignments, cancel it before it starts, and add what you paid for it once approved"
        />

        <div class="flex justify-end">
            <RequestTravelDialog :today="today" :assignments="assignments" />
        </div>

        <section class="space-y-2">
            <h2 class="text-sm font-medium">Your Travel Requests</h2>
            <TravelRequestList
                :travel-requests="travelRequests"
                :editing="{ today, currency }"
                empty-message="Your Travel Requests appear here. You haven't made any yet."
            />
        </section>
    </PageContainer>
</template>
