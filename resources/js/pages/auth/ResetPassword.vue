<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import NewPasswordFields from '@/components/auth/NewPasswordFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TextLink from '@/components/TextLink.vue';
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
        <NewPasswordFields
            :email="email"
            :password-rules="passwordRules"
            :errors="errors"
            password-label="New password"
        />

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
