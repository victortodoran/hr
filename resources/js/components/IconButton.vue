<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

/**
 * An icon-only `ghost` button that names itself twice, in its `aria-label`
 * and in a Tooltip with the same words (rule F20). Its other attributes,
 * such as `@click` and `disabled`, go to the button.
 */
defineOptions({ inheritAttrs: false });

defineProps<{
    /** What the button does, such as "Next day". */
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
