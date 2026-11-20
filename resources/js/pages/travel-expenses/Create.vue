<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import PageContainer from '@/components/PageContainer.vue';
import TravelExpenseForm from '@/components/travel-requests/TravelExpenseForm.vue';
import { formatDateRange } from '@/lib/dates';
import { index, show } from '@/routes/travel-requests';
import { create } from '@/routes/travel-requests/expenses';
import type { TravelCurrency, TravelRequestName } from '@/types';

const props = defineProps<{
    travelRequest: TravelRequestName;
    currency: TravelCurrency;
    today: string;
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Travel', href: index() },
        {
            title: props.travelRequest.destination,
            href: show(props.travelRequest.id),
        },
        { title: 'Add expense', href: create(props.travelRequest.id) },
    ],
});
</script>

<template>
    <Head title="Add expense" />

    <PageContainer width="form">
        <PageHeader
            title="Add expense"
            :description="`${travelRequest.destination} · ${formatDateRange(travelRequest.first_date, travelRequest.last_date)}`"
        />

        <p class="text-sm text-muted-foreground">
            Money you paid yourself for this travel, in {{ currency.code }}.
            Note an amount paid in another currency in the description.
        </p>

        <TravelExpenseForm
            :today="today"
            :currency="currency"
            :travel-request-id="travelRequest.id"
        />
    </PageContainer>
</template>
