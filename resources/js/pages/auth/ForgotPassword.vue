<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import Callout from '@/components/Callout.vue';
import InputError from '@/components/InputError.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TextLink from '@/components/TextLink.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Forgot password',
        description: 'We will email you a link to choose a new password.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Forgot password" />

    <Form
        v-bind="email.form()"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-5"
    >
        <Callout v-if="status" tone="info">{{ status }}</Callout>

        <div class="grid gap-2">
            <Label for="email">Email</Label>
            <Input
                id="email"
                type="email"
                name="email"
                required
                autocomplete="email"
                v-focus
                placeholder="e.g. cara@company.com"
                :aria-invalid="errors.email ? true : undefined"
            />
            <InputError :message="errors.email" />
        </div>

        <SubmitButton
            :processing="processing"
            class="w-full"
            data-test="email-password-reset-link-button"
            >Email reset link</SubmitButton
        >

        <p class="text-center text-meta">
            <TextLink :href="login()">Back to log in</TextLink>
        </p>
    </Form>
</template>
