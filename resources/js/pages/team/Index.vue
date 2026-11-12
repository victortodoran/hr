<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import ValueOr from '@/components/ValueOr.vue';
import { show } from '@/routes/employees';
import { index } from '@/routes/team';
import type { DirectReport } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Team', href: index() }],
    },
});

defineProps<{
    directReports: DirectReport[];
}>();
</script>

<template>
    <Head title="Team" />

    <div class="flex max-w-4xl flex-col gap-6 p-4">
        <Heading
            title="Team"
            description="The Employees you are the Manager of"
        />

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 font-medium">Name</th>
                        <th class="px-4 py-2 font-medium">Email</th>
                        <th class="px-4 py-2 font-medium">Job Title</th>
                        <th class="px-4 py-2 font-medium">Department</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="report in directReports" :key="report.id">
                        <td class="px-4 py-2 font-medium">
                            <Link
                                v-if="report.can_view"
                                :href="show(report.id)"
                                class="underline-offset-4 hover:underline"
                                >{{ report.name }}</Link
                            >
                            <template v-else>{{ report.name }}</template>
                        </td>
                        <td class="px-4 py-2">
                            <a
                                :href="`mailto:${report.email}`"
                                class="underline-offset-4 hover:underline"
                                >{{ report.email }}</a
                            >
                        </td>
                        <td class="px-4 py-2">
                            <ValueOr :value="report.job_title" />
                        </td>
                        <td class="px-4 py-2">{{ report.department }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
