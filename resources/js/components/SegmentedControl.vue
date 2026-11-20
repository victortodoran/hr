<script setup lang="ts" generic="Value extends string">
import type { Component } from 'vue';
import { ToggleGroupItem, ToggleGroupRoot } from 'reka-ui';

/**
 * Switches the view of the same data, or a choice of at most three
 * options (rules K3, V8): List · Calendar, Paid · Unpaid. One option is
 * always on; the one on sits raised on the track, and ←/→ move between
 * them.
 */
const props = defineProps<{
    options: { value: Value; label: string; icon?: Component }[];
    /** Names the choice for screen readers, such as "View". */
    label: string;
    size?: 'default' | 'sm';
}>();

const model = defineModel<Value>({ required: true });

/** Keeps one option on: clicking the one already on changes nothing. */
function choose(value: unknown): void {
    if (typeof value === 'string' && value !== '') {
        model.value = value as Value;
    }
}
</script>

<template>
    <ToggleGroupRoot
        data-slot="segmented-control"
        type="single"
        :model-value="model"
        :aria-label="label"
        class="inline-flex items-center gap-0.5 rounded-lg bg-muted p-0.5"
        @update:model-value="choose"
    >
        <ToggleGroupItem
            v-for="option in props.options"
            :key="option.value"
            :value="option.value"
            class="inline-flex items-center gap-1.5 rounded-md px-3 font-medium text-muted-foreground transition-colors outline-none hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring data-[state=on]:bg-background data-[state=on]:text-foreground data-[state=on]:shadow-xs [&_svg]:size-3.5"
            :class="size === 'sm' ? 'h-7 text-meta' : 'h-8 text-body'"
        >
            <component
                :is="option.icon"
                v-if="option.icon"
                aria-hidden="true"
            />
            {{ option.label }}
        </ToggleGroupItem>
    </ToggleGroupRoot>
</template>
