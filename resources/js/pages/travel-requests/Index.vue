<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import ScopeSwitch from '@/components/ScopeSwitch.vue';
import PageContainer from '@/components/PageContainer.vue';
import TravelRequestList from '@/components/travel-requests/TravelRequestList.vue';
import { Button } from '@/components/ui/button';
import { create, index } from '@/routes/travel-requests';
import type { TravelHistory } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Travel', href: index() }],
    },
});

defineProps<{
    travelRequests: TravelHistory;
}>();
</script>

<template>
    <Head title="Travel" />

    <PageContainer width="full">
        <PageHeader
            title="Travel"
            description="Your travel for work and what you paid for it"
        >
            <template #actions>
                <Button as-child>
                    <Link :href="create()">Request travel</Link>
                </Button>
            </template>
        </PageHeader>
        <ScopeSwitch area="travel" />

        <TravelRequestList
            :travel-requests="travelRequests"
            :empty-messages="{
                upcoming:
                    'Travel you ask for appears here until its last day. You have none coming up.',
                past: 'Travel that has ended, was declined or was cancelled appears here.',
            }"
        />
    </PageContainer>
</template>
