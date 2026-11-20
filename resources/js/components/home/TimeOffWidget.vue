<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Sun } from '@lucide/vue';
import { computed } from 'vue';
import HomeWidget from '@/components/home/HomeWidget.vue';
import StatStrip from '@/components/StatStrip.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { formatDateRange } from '@/lib/dates';
import { formatDays } from '@/lib/time-off';
import { create as requestTimeOff, index as timeOff } from '@/routes/time-off';
import type { PaidDaysLeftInYear, TimeOffDetails } from '@/types';

const props = defineProps<{
    paidDaysLeft: PaidDaysLeftInYear[];
    nextTimeOff: TimeOffDetails | null;
}>();

const stats = computed(() =>
    props.paidDaysLeft.map((inYear) => ({
        label: `Paid Days left in ${inYear.year}`,
        value: String(inYear.days),
    })),
);
</script>

<template>
    <HomeWidget title="Time Off" :href="timeOff()" link-label="Open Time Off">
        <StatStrip :stats="stats" flush />
        <div
            class="flex min-h-11 items-center gap-3 border-t pt-3 text-body"
            data-test="next-time-off"
        >
            <Sun
                class="size-4 shrink-0 text-muted-foreground"
                aria-hidden="true"
            />
            <template v-if="nextTimeOff !== null">
                <div class="min-w-0 flex-1">
                    <p class="font-medium tabular-nums">
                        Next:
                        {{
                            formatDateRange(
                                nextTimeOff.first_date,
                                nextTimeOff.last_date,
                            )
                        }}
                    </p>
                    <p class="text-caption text-muted-foreground">
                        {{ formatDays(nextTimeOff.days) }} ·
                        {{ nextTimeOff.is_paid ? 'Paid' : 'Unpaid'
                        }}<template v-if="nextTimeOff.decided_by !== null">
                            · decided by {{ nextTimeOff.decided_by }}</template
                        >
                    </p>
                </div>
                <StatusBadge :state="nextTimeOff.state" />
            </template>
            <template v-else>
                <p class="min-w-0 flex-1 text-muted-foreground">
                    You have no upcoming Time Off.
                </p>
                <Button
                    variant="secondary"
                    size="sm"
                    class="shrink-0 max-sm:h-11"
                    as-child
                >
                    <Link :href="requestTimeOff()">Request Time Off</Link>
                </Button>
            </template>
        </div>
    </HomeWidget>
</template>
