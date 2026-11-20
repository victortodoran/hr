<script setup lang="ts">
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import HomeWidget from '@/components/home/HomeWidget.vue';
import WidgetRows from '@/components/home/WidgetRows.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { formatDate, formatDateRange } from '@/lib/dates';
import { formatHours } from '@/lib/work-entries';
import { index as timeOff } from '@/routes/time-off';
import { show as travelRequest } from '@/routes/travel-requests';
import { edit as editTravelExpense } from '@/routes/travel-requests/expenses';
import { index as workEntries } from '@/routes/work-entries';
import type { NeedsAttention, NeedsAttentionItem } from '@/types';

const props = defineProps<{
    needsAttention: NeedsAttention;
}>();

type Href = NonNullable<InertiaLinkProps['href']>;

function describe(item: NeedsAttentionItem): {
    name: string;
    href: Href;
    edit: Href | null;
} {
    switch (item.kind) {
        case 'work-entry': {
            const week = workEntries({ query: { week: item.date } });

            return {
                name: `Work Entry · ${formatDate(item.date)}, ${item.project}, ${formatHours(item.hours)}`,
                href: week,
                edit: week,
            };
        }
        case 'travel-request':
            return {
                name: `Travel Request · ${item.destination}, ${formatDateRange(item.first_date, item.last_date)}`,
                href: travelRequest(item.id),
                edit: null,
            };
        case 'travel-expense':
            return {
                name: `Travel Expense · ${item.description} (${item.destination})`,
                href: travelRequest(item.travel_request_id),
                edit: editTravelExpense({
                    travelRequest: item.travel_request_id,
                    travelExpense: item.id,
                }),
            };
        case 'time-off':
            return {
                name: `Time Off · ${formatDateRange(item.first_date, item.last_date)}`,
                href: timeOff(),
                edit: null,
            };
    }
}

const rows = computed(() =>
    props.needsAttention.items.map((item) => ({
        key: `${item.kind}-${item.id}`,
        reason: item.decline_reason,
        ...describe(item),
    })),
);
</script>

<template>
    <HomeWidget
        title="Needs your attention"
        description="What your Manager declined, and why"
        data-test="needs-attention"
    >
        <WidgetRows :rows="rows" :row-key="(row) => row.key">
            <template #default="{ row }">
                <div class="flex min-h-11 items-start gap-3 py-2.5 text-body">
                    <StatusBadge state="declined" class="mt-0.5" />
                    <div class="min-w-0 flex-1">
                        <TextLink
                            :href="row.href"
                            class="font-medium break-words"
                            >{{ row.name }}</TextLink
                        >
                        <p class="line-clamp-2 text-meta text-muted-foreground">
                            “{{ row.reason }}”
                        </p>
                    </div>
                    <Button
                        v-if="row.edit !== null"
                        variant="secondary"
                        size="sm"
                        class="shrink-0 max-sm:h-11"
                        as-child
                    >
                        <Link :href="row.edit"
                            >Edit<span class="sr-only">
                                {{ row.name }}</span
                            ></Link
                        >
                    </Button>
                </div>
            </template>
        </WidgetRows>
        <p
            v-if="needsAttention.more > 0"
            class="text-meta text-muted-foreground"
        >
            And {{ needsAttention.more }} more not shown here.
        </p>
    </HomeWidget>
</template>
