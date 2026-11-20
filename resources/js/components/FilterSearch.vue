<script setup lang="ts">
import { useDebounceFn } from '@vueuse/core';
import { ref, watch } from 'vue';
import { Input } from '@/components/ui/input';

/** Emits once typing pauses, so the list reloads once per search rather than once per key. */
const props = withDefaults(
    defineProps<{
        modelValue: string;
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
