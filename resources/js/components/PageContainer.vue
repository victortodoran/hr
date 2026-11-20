<script setup lang="ts">
/**
 * The column every page sits in (rule L2): `full` grows with the screen up
 * to 1600px, for lists, details, dashboards and settings; `form` is a
 * 672px column for form pages. Gutters follow F13 and the vertical rhythm
 * F14: 20px from the PageHeader to the content, 32px between sections.
 */
withDefaults(
    defineProps<{
        width?: 'full' | 'form';
    }>(),
    { width: 'full' },
);
</script>

<template>
    <div
        data-slot="page-container"
        class="flex w-full flex-col px-4 pt-6 pb-10 sm:px-6 lg:px-10 [&>*+*]:mt-8 [&>[data-slot=page-header]+*]:mt-5 [&>[data-slot=scope-switch]+*]:mt-5"
        :class="width === 'form' ? 'max-w-2xl' : 'max-w-[100rem]'"
    >
        <slot />
    </div>
</template>
