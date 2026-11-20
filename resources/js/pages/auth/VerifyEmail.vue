<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import Callout from '@/components/Callout.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TextLink from '@/components/TextLink.vue';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Verify your email',
        description: 'Open the link we emailed you to verify your address.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Verify your email" />

    <Form
        v-bind="send.form()"
        v-slot="{ processing }"
        class="flex flex-col gap-5"
    >
        <Callout v-if="status === 'verification-link-sent'" tone="info"
            >We sent a new verification link to your email address.</Callout
        >

        <SubmitButton
            :processing="processing"
            variant="secondary"
            class="w-full"
            >Email a new link</SubmitButton
        >

        <p class="text-center text-meta">
            <TextLink :href="logout()" as="button">Log out</TextLink>
        </p>
    </Form>
</template>
