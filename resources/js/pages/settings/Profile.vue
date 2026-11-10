<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import DeleteAccount from '@/components/DeleteAccount.vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Profile settings',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const employee = computed(() => page.props.auth.employee);
</script>

<template>
    <Head title="Profile settings" />

    <h1 class="sr-only">Profile settings</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Profile"
            description="Your name and email address"
        />

        <dl class="grid gap-4 text-sm">
            <div class="grid gap-1">
                <dt class="font-medium">Name</dt>
                <dd class="text-muted-foreground">{{ employee.name }}</dd>
            </div>
            <div class="grid gap-1">
                <dt class="font-medium">Email address</dt>
                <dd class="text-muted-foreground">{{ employee.email }}</dd>
            </div>
        </dl>

        <p class="text-sm text-muted-foreground">
            To change your name or email, ask HR.
        </p>

        <div v-if="page.props.mustVerifyEmail && !employee.email_verified_at">
            <p class="text-sm text-muted-foreground">
                Your email address is unverified.
                <Link
                    :href="send()"
                    as="button"
                    class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                >
                    Click here to re-send the verification email.
                </Link>
            </p>

            <div
                v-if="page.props.status === 'verification-link-sent'"
                class="mt-2 text-sm font-medium text-green-600"
            >
                A new verification link has been sent to your email address.
            </div>
        </div>
    </div>

    <DeleteAccount />
</template>
