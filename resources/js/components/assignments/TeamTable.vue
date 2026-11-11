<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import ShareOfTime from '@/components/assignments/ShareOfTime.vue';
import ValueOr from '@/components/ValueOr.vue';
import { formatPeriod } from '@/lib/dates';
import { index } from '@/routes/employees/assignments';
import type { TeamAssignment } from '@/types';

defineProps<{
    assignments: TeamAssignment[];
    showProject?: boolean;
    empty: string;
}>();
</script>

<template>
    <div class="overflow-x-auto rounded-lg border">
        <table class="w-full text-sm">
            <thead class="bg-muted/50 text-left text-muted-foreground">
                <tr>
                    <th class="px-4 py-2 font-medium">Employee</th>
                    <th v-if="showProject" class="px-4 py-2 font-medium">
                        Project
                    </th>
                    <th class="px-4 py-2 font-medium">Job Title</th>
                    <th class="px-4 py-2 font-medium">Dates</th>
                    <th class="px-4 py-2 text-right font-medium">Share</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <tr v-for="assignment in assignments" :key="assignment.id">
                    <td class="px-4 py-2 font-medium">
                        <Link
                            :href="index(assignment.employee.id)"
                            class="underline-offset-4 hover:underline"
                            >{{ assignment.employee.name }}</Link
                        >
                    </td>
                    <td v-if="showProject" class="px-4 py-2">
                        {{ assignment.project?.name }}
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
                </tr>
                <tr v-if="assignments.length === 0">
                    <td
                        :colspan="showProject ? 5 : 4"
                        class="px-4 py-6 text-center text-muted-foreground"
                    >
                        {{ empty }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
