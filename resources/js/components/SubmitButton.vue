<script setup lang="ts">
import type { ButtonVariants } from '@/components/ui/button';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

/**
 * The submit button every form uses: disabled and showing a spinner while the
 * form is saving.
 */
defineProps<{
    processing: boolean;
    variant?: ButtonVariants['variant'];
    size?: ButtonVariants['size'];
}>();
</script>

<template>
    <Button
        type="submit"
        :variant="variant"
        :size="size"
        :disabled="processing"
    >
        <Spinner v-if="processing" />
        <slot />
    </Button>
</template>
