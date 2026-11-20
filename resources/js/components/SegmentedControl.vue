<script setup lang="ts" generic="Value extends string">
import type { Component } from 'vue';
import { ToggleGroupItem, ToggleGroupRoot } from 'reka-ui';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

/**
 * Switches the view of the same data, or a choice of at most three
 * options (rules K3, V8): List · Calendar, Paid · Unpaid. One option is
 * always on; the one on sits raised on the track, and ←/→ move between
 * them. An option can be disabled, and can explain itself in a Tooltip,
 * which also shows on a disabled one (the Role matrix's ScopeControl, V8).
 */
const props = defineProps<{
    options: {
        value: Value;
        label: string;
        icon?: Component;
        disabled?: boolean;
        /** Explains the option on hover and focus. */
        tooltip?: string;
    }[];
    /** Names the choice for screen readers, such as "View". */
    label: string;
    size?: 'default' | 'sm';
    disabled?: boolean;
    /** Spans its container on a phone, with 44px targets (P6). */
    fillOnPhone?: boolean;
}>();

const model = defineModel<Value>({ required: true });

/** Keeps one option on: clicking the one already on changes nothing. */
function choose(value: unknown): void {
    if (typeof value === 'string' && value !== '') {
        model.value = value as Value;
    }
}

const itemClass =
    'inline-flex items-center justify-center gap-1.5 rounded-md px-3 font-medium text-muted-foreground transition-colors outline-none hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:text-muted-foreground data-[state=on]:bg-background data-[state=on]:text-foreground data-[state=on]:ring-1 data-[state=on]:ring-border [&_svg]:size-4';
</script>

<template>
    <ToggleGroupRoot
        data-slot="segmented-control"
        type="single"
        :model-value="model"
        :aria-label="label"
        :disabled="disabled"
        class="inline-flex items-center gap-0.5 rounded-lg bg-muted p-0.5"
        :class="{ 'max-sm:flex max-sm:w-full': fillOnPhone }"
        @update:model-value="choose"
    >
        <template v-for="option in props.options" :key="option.value">
            <Tooltip v-if="option.tooltip">
                <TooltipTrigger as-child>
                    <span
                        class="inline-flex"
                        :class="{ 'max-sm:flex-1': fillOnPhone }"
                    >
                        <ToggleGroupItem
                            :value="option.value"
                            :disabled="disabled || option.disabled"
                            :class="[
                                itemClass,
                                size === 'sm'
                                    ? 'h-7 text-meta'
                                    : 'h-8 text-body',
                                { 'max-sm:h-11 max-sm:w-full': fillOnPhone },
                            ]"
                        >
                            <component
                                :is="option.icon"
                                v-if="option.icon"
                                aria-hidden="true"
                            />
                            {{ option.label }}
                        </ToggleGroupItem>
                    </span>
                </TooltipTrigger>
                <TooltipContent>{{ option.tooltip }}</TooltipContent>
            </Tooltip>
            <ToggleGroupItem
                v-else
                :value="option.value"
                :disabled="disabled || option.disabled"
                :class="[
                    itemClass,
                    size === 'sm' ? 'h-7 text-meta' : 'h-8 text-body',
                    { 'max-sm:h-11 max-sm:flex-1': fillOnPhone },
                ]"
            >
                <component
                    :is="option.icon"
                    v-if="option.icon"
                    aria-hidden="true"
                />
                {{ option.label }}
            </ToggleGroupItem>
        </template>
    </ToggleGroupRoot>
</template>
