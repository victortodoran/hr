<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    icon: Component;
    message: string;
}>();
</script>

<template>
    <div
        class="flex flex-col items-center gap-3 px-4 py-10 text-center text-sm text-muted-foreground"
    >
        <component :is="icon" class="size-8" aria-hidden="true" />
        <p class="max-w-sm">{{ message }}</p>
        <div v-if="$slots.action">
            <slot name="action" />
        </div>
    </div>
</template>
