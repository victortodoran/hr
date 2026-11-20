<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';

/**
 * The app's neutral mark (G14): a simple glyph in a 28px rounded square,
 * followed by the app's name.
 */
const name = usePage().props.name;
</script>

<template>
    <span data-slot="app-mark" class="inline-flex items-center gap-2.5">
        <span
            class="grid size-7 shrink-0 place-items-center rounded-md bg-foreground text-background"
        >
            <svg
                viewBox="0 0 24 24"
                class="size-4"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <path d="M4 20 12 4l8 16" />
                <path d="M8 13h8" />
            </svg>
        </span>
        <span class="truncate text-section">{{ name }}</span>
    </span>
</template>
