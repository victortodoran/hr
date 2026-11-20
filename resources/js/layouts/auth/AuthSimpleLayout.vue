<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppMark from '@/components/AppMark.vue';
import { home } from '@/routes';

/**
 * The auth card (rule L17): a centred card, at most 400px wide, on the
 * `bg-surface` page, with the AppMark, the title, a one-line description,
 * then the page's form and the one secondary link under its button. On a
 * phone the card fills the width with a 16px gutter.
 */
defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center bg-surface px-4 py-10"
    >
        <main
            data-slot="auth-card"
            class="flex w-full max-w-100 flex-col gap-6 rounded-lg border border-border bg-background px-6 py-8 sm:px-8"
        >
            <div class="flex flex-col items-center gap-4 text-center">
                <Link :href="home()" class="rounded-md">
                    <AppMark />
                </Link>
                <div class="flex flex-col gap-1.5">
                    <h1 class="font-display text-title-sm">{{ title }}</h1>
                    <p
                        v-if="description"
                        class="text-body text-muted-foreground"
                    >
                        {{ description }}
                    </p>
                </div>
            </div>
            <slot />
        </main>
    </div>
</template>
