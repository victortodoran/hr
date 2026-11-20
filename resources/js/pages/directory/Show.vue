<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import ReportingChain from '@/components/employees/ReportingChain.vue';
import PageHeader from '@/components/PageHeader.vue';
import PageContainer from '@/components/PageContainer.vue';
import { Button } from '@/components/ui/button';
import { index, show } from '@/routes/directory';
import { show as employeePage } from '@/routes/employees';
import type { ReportingChainView } from '@/types';

const props = defineProps<{
    reportingChain: ReportingChainView;
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Directory', href: index() },
        {
            title: props.reportingChain.employee.name,
            href: show(props.reportingChain.employee.id),
        },
    ],
});
</script>

<template>
    <Head :title="`${reportingChain.employee.name}'s Reporting Chain`" />

    <PageContainer width="form">
        <PageHeader
            :title="`${reportingChain.employee.name}'s Reporting Chain`"
            description="Who they report to, up to the top of the company, and whom they are the Manager of"
        >
            <template
                v-if="reportingChain.employee.link === 'employee'"
                #actions
            >
                <Button variant="secondary" as-child>
                    <Link :href="employeePage(reportingChain.employee.id)"
                        >Employee page</Link
                    >
                </Button>
            </template>
        </PageHeader>

        <ReportingChain :chain="reportingChain" />
    </PageContainer>
</template>
