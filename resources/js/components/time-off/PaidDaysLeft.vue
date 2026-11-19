<script setup lang="ts">
import type { PaidDaysLeftInYear } from '@/types';

defineProps<{
    /** This year's Paid Days left and, from October, next year's. */
    paidDaysLeft: PaidDaysLeftInYear[];
    /** The Employee's Paid Days, shown as "of 20" when given. */
    paidDays?: number;
}>();
</script>

<template>
    <p class="flex flex-wrap items-baseline gap-x-3 gap-y-1 text-sm">
        <template v-for="(inYear, position) in paidDaysLeft" :key="inYear.year">
            <span
                v-if="position > 0"
                class="text-muted-foreground"
                aria-hidden="true"
                >·</span
            >
            <span>
                <span
                    class="text-2xl font-semibold tabular-nums"
                    :class="{ 'text-destructive': inYear.days < 0 }"
                    >{{ inYear.days }}</span
                >
                <span class="ml-1.5 text-muted-foreground"
                    ><template v-if="paidDays !== undefined"
                        >of {{ paidDays }} </template
                    >Paid Days left in {{ inYear.year }}</span
                >
            </span>
        </template>
    </p>
</template>
