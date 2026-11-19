<script setup lang="ts">
/**
 * The column every page sits in: narrow for forms, wide for lists.
 */
withDefaults(
    defineProps<{
        width?: 'narrow' | 'wide';
    }>(),
    { width: 'wide' },
);
</script>

<template>
    <div
        class="flex w-full flex-col gap-6 p-4"
        :class="width === 'narrow' ? 'max-w-xl' : 'max-w-5xl'"
    >
        <slot />
    </div>
</template>
