<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import Callout from '@/components/Callout.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TextLink from '@/components/TextLink.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Log in',
        description: 'Enter your email and password to continue.',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Log in" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
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
                v-focus
                autocomplete="email"
                placeholder="e.g. cara@company.com"
                :aria-invalid="errors.email ? true : undefined"
            />
            <InputError :message="errors.email" />
        </div>

        <div class="grid gap-2">
            <Label for="password">Password</Label>
            <PasswordInput
                id="password"
                name="password"
                required
                autocomplete="current-password"
                :aria-invalid="errors.password ? true : undefined"
            />
            <InputError :message="errors.password" />
        </div>

        <Label for="remember" class="flex items-center gap-3">
            <Checkbox id="remember" name="remember" />
            <span>Remember me</span>
        </Label>

        <SubmitButton
            :processing="processing"
            class="w-full"
            data-test="login-button"
            >Log in</SubmitButton
        >

        <p v-if="canResetPassword" class="text-center text-meta">
            <TextLink :href="request()">Forgot your password?</TextLink>
        </p>
    </Form>
</template>
