<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import PageContainer from '@/components/PageContainer.vue';
import TravelExpenseForm from '@/components/travel-requests/TravelExpenseForm.vue';
import { formatDateRange } from '@/lib/dates';
import { index, show } from '@/routes/travel-requests';
import { edit } from '@/routes/travel-requests/expenses';
import type {
    TravelCurrency,
    TravelExpenseTyped,
    TravelRequestName,
} from '@/types';

const props = defineProps<{
    travelRequest: TravelRequestName;
    currency: TravelCurrency;
    today: string;
    travelExpense: TravelExpenseTyped;
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Travel', href: index() },
        {
            title: props.travelRequest.destination,
            href: show(props.travelRequest.id),
        },
        {
            title: 'Change expense',
            href: edit({
                travelRequest: props.travelRequest.id,
                travelExpense: props.travelExpense.id,
            }),
        },
    ],
});
</script>

<template>
    <Head title="Change expense" />

    <PageContainer width="narrow">
        <Heading
            title="Change expense"
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
            :expense="travelExpense"
        />
    </PageContainer>
</template>
