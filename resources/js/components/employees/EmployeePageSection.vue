<script setup lang="ts">
/**
 * One section of an Employee's page, the same on every tab: a heading, an
 * optional line saying what the section holds, an optional action beside
 * the heading in the `action` slot, and the section's content below.
 */
defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <section class="flex min-w-0 flex-col gap-4">
        <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
            <div class="space-y-1">
                <h2 class="text-base font-semibold">{{ title }}</h2>
                <p v-if="description" class="text-sm text-muted-foreground">
                    {{ description }}
                </p>
            </div>
            <slot name="action" />
        </div>
        <slot />
    </section>
</template>
