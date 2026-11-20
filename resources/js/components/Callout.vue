<script setup lang="ts">
import { CircleX, Info, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    tone: 'info' | 'attention' | 'declined';
}>();

defineSlots<{
    default: () => unknown;
    action?: () => unknown;
}>();

const look = computed(
    () =>
        ({
            info: {
                icon: Info,
                box: 'border-border bg-surface',
                icon_colour: 'text-muted-foreground',
            },
            attention: {
                icon: TriangleAlert,
                box: 'border-transparent bg-status-attention text-status-attention-foreground',
                icon_colour: 'text-status-attention-icon',
            },
            declined: {
                icon: CircleX,
                box: 'border-transparent bg-status-declined text-status-declined-foreground',
                icon_colour: 'text-status-declined-icon',
            },
        })[props.tone],
);
</script>

<template>
    <div
        data-slot="callout"
        :data-tone="tone"
        class="flex flex-wrap items-start gap-x-2.5 gap-y-2 rounded-lg border px-3 py-2.5 text-body"
        :class="look.box"
    >
        <component
            :is="look.icon"
            class="mt-0.5 size-4 shrink-0"
            :class="look.icon_colour"
            aria-hidden="true"
        />
        <div class="min-w-0 flex-1"><slot /></div>
        <div
            v-if="$slots.action"
            class="shrink-0 max-sm:basis-full max-sm:pl-6.5"
        >
            <slot name="action" />
        </div>
    </div>
</template>
