<script setup lang="ts">
defineProps<{
    facts: { label: string; value: string | null }[];
}>();

defineSlots<{
    [slot: `fact-${string}`]: () => unknown;
}>();
</script>

<template>
    <dl
        data-slot="fact-list"
        class="grid gap-x-6 gap-y-3 sm:grid-cols-[9rem_1fr]"
    >
        <template v-for="fact in facts" :key="fact.label">
            <dt class="text-meta text-muted-foreground">{{ fact.label }}</dt>
            <dd class="min-w-0 break-words max-sm:-mt-2">
                <slot :name="`fact-${fact.label}`">
                    <template v-if="fact.value">{{ fact.value }}</template>
                    <span v-else class="text-subtle-foreground">Not set</span>
                </slot>
            </dd>
        </template>
    </dl>
</template>
