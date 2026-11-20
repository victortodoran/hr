<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

defineOptions({ inheritAttrs: false });

defineProps<{
    label: string;
}>();
</script>

<template>
    <Tooltip>
        <TooltipTrigger as-child>
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                :aria-label="label"
                v-bind="$attrs"
            >
                <slot />
            </Button>
        </TooltipTrigger>
        <TooltipContent>{{ label }}</TooltipContent>
    </Tooltip>
</template>
