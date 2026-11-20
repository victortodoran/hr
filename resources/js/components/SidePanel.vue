<script setup lang="ts">
/**
 * A page's side column (rule L6) as a hairline-bordered `bg-surface`
 * panel, never a Card (L25). It sticks to the top while the main column
 * scrolls once the column sits beside it, from 1280px. Name it with
 * `aria-label` or `aria-labelledby`.
 */
</script>

<template>
    <aside
        data-slot="side-panel"
        class="flex flex-col gap-5 rounded-lg border bg-surface p-5 xl:sticky xl:top-6"
    >
        <slot />
    </aside>
</template>
