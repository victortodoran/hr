<script setup lang="ts">
import { Check } from '@lucide/vue';

/**
 * The brief "Saved" tick beside a dropdown that saves on pick, read out
 * to screen readers as it shows.
 */
defineProps<{
    /** Whether the tick shows. */
    shown: boolean;
}>();
</script>

<template>
    <span
        class="inline-flex items-center gap-1 text-sm text-muted-foreground"
        aria-live="polite"
    >
        <template v-if="shown">
            <Check class="size-4" aria-hidden="true" />
            Saved
        </template>
    </span>
</template>
