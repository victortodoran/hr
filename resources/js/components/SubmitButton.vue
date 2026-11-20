<script setup lang="ts">
import type { ButtonVariants } from '@/components/ui/button';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

defineProps<{
    processing: boolean;
    variant?: ButtonVariants['variant'];
    size?: ButtonVariants['size'];
    disabled?: boolean;
    type?: 'submit' | 'button';
}>();
</script>

<template>
    <Button
        :type="type ?? 'submit'"
        :variant="variant"
        :size="size"
        :disabled="processing || disabled"
    >
        <Spinner v-if="processing" />
        <slot />
    </Button>
</template>
