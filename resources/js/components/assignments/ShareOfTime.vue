<script setup lang="ts">
defineProps<{
    /** Only empty on the Internal Assignment, which then shows 0% until a share is planned. */
    share: number | null;
}>();
</script>

<template>
    <span>{{ share ?? 0 }}%</span>
</template>
