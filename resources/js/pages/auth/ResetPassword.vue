<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TextLink from '@/components/TextLink.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { login } from '@/routes';
import { update } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Reset password',
        description: 'Choose a new password for your account.',
    },
});

defineProps<{
    token: string;
    email: string;
    passwordRules: string;
}>();
</script>

<template>
    <Head title="Reset password" />

    <Form
        v-bind="update.form()"
        :transform="(data) => ({ ...data, token, email })"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-5"
    >
        <div class="grid gap-2">
            <Label for="email">Email</Label>
            <Input
                id="email"
                type="email"
                autocomplete="email"
                :default-value="email"
                class="bg-surface text-muted-foreground"
                readonly
            />
            <InputError :message="errors.email" />
        </div>

        <div class="grid gap-2">
            <Label for="password">New password</Label>
            <PasswordInput
                id="password"
                name="password"
                autocomplete="new-password"
                autofocus
                :passwordrules="passwordRules"
                :aria-invalid="errors.password ? true : undefined"
            />
            <InputError :message="errors.password" />
        </div>

        <div class="grid gap-2">
            <Label for="password_confirmation">Confirm password</Label>
            <PasswordInput
                id="password_confirmation"
                name="password_confirmation"
                autocomplete="new-password"
                :passwordrules="passwordRules"
                :aria-invalid="errors.password_confirmation ? true : undefined"
            />
            <InputError :message="errors.password_confirmation" />
        </div>

        <SubmitButton
            :processing="processing"
            class="w-full"
            data-test="reset-password-button"
            >Reset password</SubmitButton
        >

        <p class="text-center text-meta">
            <TextLink :href="login()">Back to log in</TextLink>
        </p>
    </Form>
</template>
