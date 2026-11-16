<script setup lang="ts">
import type { ButtonVariants } from '@/components/ui/button';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

/**
 * The submit button every form uses: disabled and showing a spinner while the
 * form is saving, and disabled too when the form can't be sent yet.
 */
defineProps<{
    processing: boolean;
    variant?: ButtonVariants['variant'];
    size?: ButtonVariants['size'];
    disabled?: boolean;
    /** "button" when it runs a click handler outside any form. */
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
