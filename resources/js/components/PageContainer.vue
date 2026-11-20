<script setup lang="ts">
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
