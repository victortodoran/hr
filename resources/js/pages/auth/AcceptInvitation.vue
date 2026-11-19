<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import AlertError from '@/components/AlertError.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TextLink from '@/components/TextLink.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/invitation';
import { login } from '@/routes';

defineOptions({
    layout: {
        title: 'Set your password',
        description: 'Choose a password to finish setting up your account',
    },
});

const props = defineProps<{
    token: string;
    email: string;
    passwordRules: string;
    refusal: string | null;
}>();

const inputEmail = ref(props.email);
</script>

<template>
    <Head title="Set your password" />

    <div v-if="refusal !== null" class="grid gap-6">
        <AlertError
            title="This invitation link no longer works"
            :errors="[refusal]"
        />
        <div class="text-center text-sm text-muted-foreground">
            Already set your password?
            <TextLink :href="login()">Log in</TextLink>
        </div>
    </div>

    <Form
        v-else
        v-bind="store.form()"
        :transform="(data) => ({ ...data, token, email })"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">Email</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="email"
                    v-model="inputEmail"
                    class="mt-1 block w-full"
                    readonly
                />
                <InputError :message="errors.email" class="mt-2" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Password</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    autocomplete="new-password"
                    class="mt-1 block w-full"
                    autofocus
                    placeholder="Password"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation"> Confirm password </Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    autocomplete="new-password"
                    class="mt-1 block w-full"
                    placeholder="Confirm password"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <SubmitButton
                :processing="processing"
                class="mt-4 w-full"
                data-test="set-password-button"
                >Set password</SubmitButton
            >
        </div>
    </Form>
</template>
