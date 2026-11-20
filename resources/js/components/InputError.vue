<script setup lang="ts">
import { CircleAlert } from '@lucide/vue';

/**
 * A field's error: icon and sentence in the destructive colour, shown in
 * place of the help text (rule V5).
 */
defineProps<{
    message?: string;
}>();
</script>

<template>
    <div v-show="message">
        <p class="flex items-start gap-1.5 text-meta text-destructive">
            <CircleAlert class="mt-px size-4 shrink-0" aria-hidden="true" />
            <span>{{ message }}</span>
        </p>
    </div>
</template>
