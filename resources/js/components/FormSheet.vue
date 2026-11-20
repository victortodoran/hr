<script setup lang="ts">
import { useMediaQuery } from '@vueuse/core';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';

/** The sheet doesn't render the form: the footer's buttons reach the slot's `<form id>` through `form="…"`, so Enter in a field submits it too. */
defineProps<{
    title: string;
    description?: string;
}>();

defineSlots<{
    default?: () => unknown;
    footer?: () => unknown;
}>();

const open = defineModel<boolean>('open', { required: true });

const isPhone = useMediaQuery('(max-width: 639px)');

const fieldSelector = [
    'input:not([type="hidden"]):not([type="file"]):not([disabled]):not([tabindex="-1"])',
    'textarea:not([disabled])',
    '[role="spinbutton"]',
    'button[role="combobox"]',
].join(', ');

/** Focuses the first field rather than the first focusable control, often a picker's icon button whose Tooltip would cover the field's label. */
function focusFirstField(event: Event): void {
    const field = (
        event.target as HTMLElement | null
    )?.querySelector<HTMLElement>(fieldSelector);

    if (field) {
        event.preventDefault();
        field.focus();
    }
}
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent
            data-slot="form-sheet"
            @open-auto-focus="focusFirstField"
            :side="isPhone ? 'bottom' : 'right'"
            class="gap-0 max-sm:h-[calc(100dvh-3rem)] max-sm:rounded-t-xl"
        >
            <SheetHeader class="pr-12">
                <SheetTitle class="text-section">{{ title }}</SheetTitle>
                <SheetDescription
                    v-if="description"
                    class="text-meta text-muted-foreground"
                    >{{ description }}</SheetDescription
                >
            </SheetHeader>
            <div class="flex-1 overflow-y-auto px-6 py-5 text-body">
                <slot />
            </div>
            <SheetFooter
                v-if="$slots.footer"
                class="flex-row justify-end gap-2 max-sm:[&>*]:h-11 max-sm:[&>*:last-child]:flex-1"
            >
                <slot name="footer" />
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
