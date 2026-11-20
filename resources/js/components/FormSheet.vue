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

/**
 * An overlay form (rule L14): a form of up to six fields in a 480px sheet
 * from the right, over the page it came from; on a phone a full-height
 * sheet from the bottom (P7). The body scrolls under the title, and the
 * `footer` slot's buttons stay at the bottom.
 *
 * The sheet doesn't render the form: put `<form id="…">` with its fields in
 * the default slot, and give the footer's buttons `form="…"` with the same
 * id, so the submit button sends it and Enter in a field does too.
 */
defineProps<{
    title: string;
    /** One line under the title, such as what the form belongs to. */
    description?: string;
}>();

defineSlots<{
    default?: () => unknown;
    footer?: () => unknown;
}>();

const open = defineModel<boolean>('open', { required: true });

const isPhone = useMediaQuery('(max-width: 639px)');

/** The kinds of element a person types or picks a value in. */
const fieldSelector = [
    'input:not([type="hidden"]):not([type="file"]):not([disabled]):not([tabindex="-1"])',
    'textarea:not([disabled])',
    '[role="spinbutton"]',
    'button[role="combobox"]',
].join(', ');

/**
 * Opens with the cursor in the first field rather than on the first
 * focusable control, which is often a picker's icon button whose Tooltip
 * would cover the field's label.
 */
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
