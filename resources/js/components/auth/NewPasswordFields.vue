<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/** The email is read-only because the link fixed it. */
defineProps<{
    email: string;
    passwordRules: string;
    errors: Partial<
        Record<'email' | 'password' | 'password_confirmation', string>
    >;
    passwordLabel?: string;
}>();
</script>

<template>
    <div class="flex flex-col gap-5">
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
            <Label for="password">{{ passwordLabel ?? 'Password' }}</Label>
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
    </div>
</template>
