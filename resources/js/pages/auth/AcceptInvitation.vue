<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import NewPasswordFields from '@/components/auth/NewPasswordFields.vue';
import Callout from '@/components/Callout.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TextLink from '@/components/TextLink.vue';
import { login } from '@/routes';
import { store } from '@/routes/invitation';

defineOptions({
    layout: {
        title: 'Set your password',
        description: 'Choose a password to finish setting up your account.',
    },
});

defineProps<{
    token: string;
    email: string;
    passwordRules: string;
    refusal: string | null;
}>();
</script>

<template>
    <Head title="Set your password" />

    <div v-if="refusal !== null" class="flex flex-col gap-5">
        <Callout tone="declined">
            <p class="font-medium">This invitation link no longer works.</p>
            <p>{{ refusal }}</p>
        </Callout>
        <p class="text-center text-meta">
            <TextLink :href="login()">Back to log in</TextLink>
        </p>
    </div>

    <Form
        v-else
        v-bind="store.form()"
        :transform="(data) => ({ ...data, token, email })"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-5"
    >
        <NewPasswordFields
            :email="email"
            :password-rules="passwordRules"
            :errors="errors"
        />

        <SubmitButton
            :processing="processing"
            class="w-full"
            data-test="set-password-button"
            >Set password</SubmitButton
        >

        <p class="text-center text-meta">
            <TextLink :href="login()">Back to log in</TextLink>
        </p>
    </Form>
</template>
