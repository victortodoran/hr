<script setup lang="ts">
import { Target } from '@lucide/vue';

/**
 * The dashed box that stands in for a year's Goal cards while there are
 * none: a sentence on whose Goals appear there, and the action to give the
 * first one when the viewer may.
 */
defineSlots<{
    default: () => unknown;
    action?: () => unknown;
}>();
</script>

<template>
    <div
        class="flex flex-col items-center gap-3.5 rounded-xl border-[1.5px] border-dashed px-6 py-14 text-center"
    >
        <span
            class="inline-flex size-12 items-center justify-center rounded-xl bg-muted text-muted-foreground"
        >
            <Target class="size-5.5" aria-hidden="true" />
        </span>
        <div class="flex max-w-sm flex-col gap-1">
            <p class="font-semibold">No Goals yet</p>
            <p class="text-sm/normal text-muted-foreground">
                <slot />
            </p>
        </div>
        <slot name="action" />
    </div>
</template>
