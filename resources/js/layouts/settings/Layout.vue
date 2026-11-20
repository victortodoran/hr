<script setup lang="ts">
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import SettingsFrame from '@/components/SettingsFrame.vue';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const items: NavItem[] = [
    { title: 'Profile', href: editProfile() },
    { title: 'Security', href: editSecurity() },
];
</script>

<template>
    <PageContainer width="full">
        <PageHeader
            title="Settings"
            description="Your profile and how you sign in."
        />
        <SettingsFrame :items="items" label="Settings">
            <section class="max-w-xl space-y-12">
                <slot />
            </section>
        </SettingsFrame>
    </PageContainer>
</template>
