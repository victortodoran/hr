<script setup lang="ts">
/**
 * A page's header (rule L3): the serif `<h1>`, an optional status badge
 * after it, one sentence of description (or a meta line through the
 * description slot) and the page's actions on the right, at most one of
 * them `primary` (L4). On a phone it stacks, with the actions full-width
 * under the description (P3).
 */
defineProps<{
    title: string;
    description?: string;
}>();

defineSlots<{
    status?: () => unknown;
    description?: () => unknown;
    actions?: () => unknown;
}>();
</script>

<template>
    <header
        data-slot="page-header"
        class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
    >
        <div class="flex min-w-0 flex-col gap-1">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <h1 class="font-display text-title-sm sm:text-title">
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
            <p v-else-if="description" class="text-body text-muted-foreground">
                {{ description }}
            </p>
        </div>
        <div
            v-if="$slots.actions"
            class="flex flex-wrap items-center gap-2 max-sm:w-full max-sm:*:flex-1"
        >
            <slot name="actions" />
        </div>
    </header>
</template>
