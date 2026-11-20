<script setup lang="ts">
defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <section class="flex min-w-0 flex-col gap-4">
        <div
            class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2"
        >
            <div class="space-y-1">
                <h2 class="text-section">{{ title }}</h2>
                <p v-if="description" class="text-sm text-muted-foreground">
                    {{ description }}
                </p>
            </div>
            <slot name="action" />
        </div>
        <slot />
    </section>
</template>
