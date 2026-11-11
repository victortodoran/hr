<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import AssignmentForm from '@/components/assignments/AssignmentForm.vue';
import ShareOfTime from '@/components/assignments/ShareOfTime.vue';
import Heading from '@/components/Heading.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import ValueOr from '@/components/ValueOr.vue';
import { formatDate, formatPeriod } from '@/lib/dates';
import { index } from '@/routes/employees/assignments';
import type { AssignmentRecord, Choice, OpenProject } from '@/types';

const props = defineProps<{
    employee: Choice;
    assignments: AssignmentRecord[];
    overBookedFrom: string | null;
    canManage: boolean;
    projects: OpenProject[];
}>();

setLayoutProps({
    breadcrumbs: [
        {
            title: `${props.employee.name}'s Assignments`,
            href: index(props.employee.id),
        },
    ],
});
</script>

<template>
    <Head :title="`${employee.name}'s Assignments`" />

    <div class="flex max-w-4xl flex-col gap-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                :title="`${employee.name}'s Assignments`"
                description="The Projects they work on, as what, when and for how much of their time"
            />
            <AssignmentForm
                v-if="canManage"
                :employee="employee"
                :projects="projects"
            />
        </div>

        <Alert v-if="overBookedFrom" variant="destructive">
            <AlertTitle>{{ employee.name }} is Over-booked</AlertTitle>
            <AlertDescription>
                Their planned shares go above 100% from
                {{ formatDate(overBookedFrom) }}.
            </AlertDescription>
        </Alert>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 font-medium">Project</th>
                        <th class="px-4 py-2 font-medium">Job Title</th>
                        <th class="px-4 py-2 font-medium">Dates</th>
                        <th class="px-4 py-2 text-right font-medium">Share</th>
                        <th v-if="canManage" class="px-4 py-2">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="assignment in assignments" :key="assignment.id">
                        <td class="px-4 py-2">
                            <p class="font-medium">
                                {{ assignment.project.name }}
                            </p>
                            <p class="text-muted-foreground">
                                {{
                                    assignment.project.is_internal
                                        ? 'Internal'
                                        : (assignment.project.client ??
                                          'No Client')
                                }}
                            </p>
                        </td>
                        <td class="px-4 py-2">
                            <ValueOr :value="assignment.job_title" />
                        </td>
                        <td class="px-4 py-2">
                            {{
                                formatPeriod(
                                    assignment.start_date,
                                    assignment.end_date,
                                )
                            }}
                        </td>
                        <td class="px-4 py-2 text-right">
                            <ShareOfTime :share="assignment.share" />
                        </td>
                        <td v-if="canManage" class="px-4 py-2 text-right">
                            <AssignmentForm
                                :employee="employee"
                                :projects="projects"
                                :assignment="assignment"
                            />
                        </td>
                    </tr>
                    <tr v-if="assignments.length === 0">
                        <td
                            :colspan="canManage ? 5 : 4"
                            class="px-4 py-6 text-center text-muted-foreground"
                        >
                            No Assignments yet.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
