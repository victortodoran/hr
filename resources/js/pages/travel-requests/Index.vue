<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
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

    <PageContainer width="wide">
        <div class="flex items-start justify-between gap-4">
            <Heading
                title="Travel"
                description="Your travel for work and what you paid for it"
            />
            <Button as-child>
                <Link :href="create()">Request travel</Link>
            </Button>
        </div>

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
