<script setup lang="ts">
import { onUnmounted, watchEffect } from 'vue';
import { usePageTitle } from '@/composables/usePageTitle';

const props = defineProps<{
    title: string;
    description?: string;
}>();

defineSlots<{
    lead?: () => unknown;
    status?: () => unknown;
    description?: () => unknown;
    actions?: () => unknown;
}>();

const pageTitle = usePageTitle();

watchEffect(() => {
    pageTitle.value = props.title;
});

onUnmounted(() => {
    if (pageTitle.value === props.title) {
        pageTitle.value = null;
    }
});
</script>

<template>
    <header
        data-slot="page-header"
        class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
    >
        <div class="flex min-w-0 items-center gap-3 sm:gap-4">
            <slot name="lead" />
            <div class="flex min-w-0 flex-col gap-1">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <h1 class="text-title-sm sm:text-title">
                        {{ title }}
                    </h1>
                    <slot name="status" />
                </div>
                <div
                    v-if="$slots.description"
                    class="flex flex-wrap items-center gap-x-2 gap-y-1 text-body text-muted-foreground"
                >
                    <slot name="description" />
                </div>
                <p
                    v-else-if="description"
                    class="text-body text-muted-foreground"
                >
                    {{ description }}
                </p>
            </div>
        </div>
        <div
            v-if="$slots.actions"
            class="flex flex-wrap items-center gap-2 max-sm:w-full max-sm:*:flex-1"
        >
            <slot name="actions" />
        </div>
    </header>
</template>
