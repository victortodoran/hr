<script setup lang="ts">
/**
 * An Assignment's Capacity as a small bar beside its percentage (rule F11),
 * such as "▰▰▱ 60%". The bar only repeats the number, so it is hidden from
 * screen readers; on a phone only the percentage shows. The percentage
 * keeps one width, so the bars line up down a right-aligned column.
 */
defineProps<{
    /** A whole percentage of the Employee's time, from 0 to 100. */
    capacity: number;
}>();
</script>

<template>
    <span data-slot="capacity-bar" class="inline-flex items-center gap-2">
        <span
            class="hidden h-1.5 w-16 overflow-hidden rounded-full bg-muted sm:block"
            aria-hidden="true"
        >
            <span
                class="block h-full rounded-full bg-chart-bar-strong"
                :style="{ width: `${capacity}%` }"
            />
        </span>
        <span class="w-10 text-right tabular-nums">{{ capacity }}%</span>
    </span>
</template>
