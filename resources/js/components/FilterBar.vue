<script setup lang="ts">
/**
 * The row of filters above a list, the same on every list (rule K5): the
 * page puts in it what the list is filtered by, usually a `FilterSearch`
 * first and then `FilterSelect`s, and they wrap onto more lines on a
 * phone. The `summary` slot sits at the bar's right with the matching
 * count and total. Actions such as "New …" sit by the page title instead.
 */
defineSlots<{
    default?: () => unknown;
    summary?: () => unknown;
}>();
</script>

<template>
    <div class="flex flex-wrap items-center gap-3" data-test="filter-bar">
        <slot />
        <div
            v-if="$slots.summary"
            class="ml-auto text-meta text-muted-foreground"
        >
            <slot name="summary" />
        </div>
    </div>
</template>
