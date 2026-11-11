<script setup lang="ts">
withDefaults(
    defineProps<{
        value: string | null;
        fallback?: string;
    }>(),
    { fallback: '—' },
);
</script>

<template>
    <span v-if="value">{{ value }}</span>
    <span v-else class="text-muted-foreground">{{ fallback }}</span>
</template>
