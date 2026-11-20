<script setup lang="ts">
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import SettingsFrame from '@/components/SettingsFrame.vue';
import { useCompanySettingsLinks } from '@/composables/useCompanySettingsLinks';

const settingsLinks = useCompanySettingsLinks();
</script>

<template>
    <PageContainer width="full">
        <PageHeader
            title="Company settings"
            description="Your company's details, its Departments, and the Roles that decide what people may do."
        />

        <SettingsFrame :items="settingsLinks" label="Company settings">
            <slot />
        </SettingsFrame>
    </PageContainer>
</template>
