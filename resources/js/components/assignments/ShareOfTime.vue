<script setup lang="ts">
import ValueOr from '@/components/ValueOr.vue';

defineProps<{
    /** Only empty on the Internal Assignment, where it means the leftover time. */
    share: number | null;
}>();
</script>

<template>
    <ValueOr :value="share === null ? null : `${share}%`" fallback="Leftover" />
</template>
