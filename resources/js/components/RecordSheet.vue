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
 * One row's full record, opened by clicking the row (rule K8): what the
 * table can't show, such as the whole description, the receipt and the
 * record's history, with the row's decisions in the footer. It slides in
 * from the right, or up from the bottom on a phone.
 */
defineProps<{
    title: string;
    /** One line under the title, such as whose record it is. */
    description?: string;
}>();

defineSlots<{
    default?: () => unknown;
    footer?: () => unknown;
}>();

const open = defineModel<boolean>('open', { required: true });

const isPhone = useMediaQuery('(max-width: 639px)');
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent
            data-slot="record-sheet"
            :side="isPhone ? 'bottom' : 'right'"
            class="max-sm:max-h-[90dvh] max-sm:rounded-t-xl"
        >
            <SheetHeader class="pr-10">
                <SheetTitle class="text-section">{{ title }}</SheetTitle>
                <SheetDescription v-if="description">{{
                    description
                }}</SheetDescription>
            </SheetHeader>
            <div
                class="flex flex-1 flex-col gap-5 overflow-y-auto px-6 text-body"
            >
                <slot />
            </div>
            <SheetFooter
                v-if="$slots.footer"
                class="flex-row justify-end gap-2 border-t"
            >
                <slot name="footer" />
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
