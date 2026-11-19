<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import ReportingChain from '@/components/employees/ReportingChain.vue';
import Heading from '@/components/Heading.vue';
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

    <div class="mx-auto flex w-full max-w-xl flex-col gap-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                :title="`${reportingChain.employee.name}'s Reporting Chain`"
                description="Who they report to, up to the top of the company, and whom they are the Manager of"
            />
            <Button
                v-if="reportingChain.employee.link === 'employee'"
                variant="outline"
                as-child
            >
                <Link :href="employeePage(reportingChain.employee.id)"
                    >Employee page</Link
                >
            </Button>
        </div>

        <ReportingChain :chain="reportingChain" />
    </div>
</template>
