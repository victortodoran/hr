<script setup lang="ts">
/**
 * A page's title, as its main heading, with an optional grey description line
 * below and the page's actions on the right; the small variant titles a
 * section within a page instead. The description slot replaces the
 * description prop when the line needs more than text, such as a status badge.
 */
type Props = {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
};

withDefaults(defineProps<Props>(), {
    variant: 'default',
});

defineSlots<{
    description?: () => unknown;
    actions?: () => unknown;
}>();
</script>

<template>
    <header v-if="variant === 'small'">
        <h2 class="mb-0.5 text-section">
            {{ title }}
        </h2>
        <p v-if="description" class="text-body text-muted-foreground">
            {{ description }}
        </p>
    </header>
    <header
        v-else
        class="mb-8 flex flex-wrap items-start justify-between gap-4"
    >
        <div class="flex min-w-0 flex-col gap-0.5">
            <h1 class="font-display text-title-sm font-normal sm:text-title">
                {{ title }}
            </h1>
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
        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>
