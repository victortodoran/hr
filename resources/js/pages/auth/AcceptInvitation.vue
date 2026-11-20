<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import Callout from '@/components/Callout.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TextLink from '@/components/TextLink.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
            <Label for="password">Password</Label>
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
            data-test="set-password-button"
            >Set password</SubmitButton
        >

        <p class="text-center text-meta">
            <TextLink :href="login()">Back to log in</TextLink>
        </p>
    </Form>
</template>
