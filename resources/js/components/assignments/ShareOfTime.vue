<script setup lang="ts">
defineProps<{
    /** Only empty on the Internal Assignment, when no share is planned. */
    share: number | null;
    /** What an unplanned internal share works out to today. */
    leftover: number | null;
}>();
</script>

<template>
    <span v-if="share !== null">{{ share }}%</span>
    <span v-else class="text-muted-foreground"
        >{{ leftover ?? 0 }}% (leftover)</span
    >
</template>
