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
