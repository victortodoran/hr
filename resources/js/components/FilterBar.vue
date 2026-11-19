<script setup lang="ts">
/**
 * The row of filters above a list, the same on every list: the page puts
 * in it what the list is filtered by, usually a `FilterSearch` first and
 * then `FilterSelect`s, and they wrap onto more lines on a phone. Actions
 * such as "New …" sit by the page title instead.
 */
</script>

<template>
    <div class="flex flex-wrap items-center gap-3" data-test="filter-bar">
        <slot />
    </div>
</template>
