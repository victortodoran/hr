<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { FileQuestionMark, ServerCrash, ShieldX } from '@lucide/vue';
import type { Component } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';

/** On the auth surface, so it works signed in or out. */
const props = defineProps<{
    status: 403 | 404 | 500;
}>();

const pages: Record<
    403 | 404 | 500,
    { title: string; message: string; icon: Component }
> = {
    403: {
        title: 'You don’t have access to this page',
        message:
            'It isn’t open to you right now. If you think it should be, ask whoever manages Roles.',
        icon: ShieldX,
    },
    404: {
        title: 'Page not found',
        message:
            'This page doesn’t exist, or what it showed has been deleted. Check the link, or start again from Home.',
        icon: FileQuestionMark,
    },
    500: {
        title: 'Something went wrong',
        message:
            'The server couldn’t finish this request. Try again in a moment, or start again from Home.',
        icon: ServerCrash,
    },
};

const page = pages[props.status];

setLayoutProps({ title: page.title });
</script>

<template>
    <Head :title="page.title" />

    <EmptyState :icon="page.icon" :message="page.message">
        <template #action>
            <Button as-child>
                <Link :href="home()">Go to Home</Link>
            </Button>
        </template>
    </EmptyState>
</template>
