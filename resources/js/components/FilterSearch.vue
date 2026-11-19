<script setup lang="ts">
import { useDebounceFn } from '@vueuse/core';
import { ref, watch } from 'vue';
import { Input } from '@/components/ui/input';

/**
 * A list's search box, for a `FilterBar`: shows what is typed straight
 * away, and emits it once typing pauses, so the list reloads once per
 * search rather than once per key. Shows the search again whenever the
 * page's props change it.
 */
const props = withDefaults(
    defineProps<{
        modelValue: string;
        /** What the box searches, as its placeholder and label say it. */
        label?: string;
    }>(),
    { label: 'Search by name' },
);

const emit = defineEmits<{
    'update:modelValue': [search: string];
}>();

const typed = ref(props.modelValue);

watch(
    () => props.modelValue,
    (search) => {
        typed.value = search;
    },
);

const emitSearch = useDebounceFn(() => {
    emit('update:modelValue', typed.value);
}, 300);
</script>

<template>
    <Input
        v-model="typed"
        type="search"
        :placeholder="label"
        :aria-label="label"
        class="w-full sm:max-w-xs"
        @update:model-value="emitSearch"
    />
</template>
