<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/password/confirm';

defineOptions({
    layout: {
        title: 'Confirm password',
        description: 'Enter your password again before you continue.',
    },
});
</script>

<template>
    <Head title="Confirm password" />

    <Form
        v-bind="store.form()"
        reset-on-success
        v-slot="{ errors, processing }"
        class="flex flex-col gap-5"
    >
        <div class="grid gap-2">
            <Label for="password">Password</Label>
            <PasswordInput
                id="password"
                name="password"
                required
                autocomplete="current-password"
                autofocus
                :aria-invalid="errors.password ? true : undefined"
            />
            <InputError :message="errors.password" />
        </div>

        <SubmitButton
            :processing="processing"
            class="w-full"
            data-test="confirm-password-button"
            >Confirm password</SubmitButton
        >
    </Form>
</template>
