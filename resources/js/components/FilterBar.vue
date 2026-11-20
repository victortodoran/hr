<script setup lang="ts">
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
