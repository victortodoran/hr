<script setup lang="ts">
import { Check } from '@lucide/vue';

defineProps<{
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
