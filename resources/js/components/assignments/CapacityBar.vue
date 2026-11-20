<script setup lang="ts">
/** The bar only repeats the number, so screen readers skip it; the percentage keeps one width so the bars line up. */
defineProps<{
    /** A whole percentage, from 0 to 100. */
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
