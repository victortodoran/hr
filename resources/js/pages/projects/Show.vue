<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import TeamTable from '@/components/assignments/TeamTable.vue';
import Heading from '@/components/Heading.vue';
import PageContainer from '@/components/PageContainer.vue';
import { formatPeriod } from '@/lib/dates';
import { index, show } from '@/routes/projects';
import type { ProjectRecord, TeamAssignment } from '@/types';

const props = defineProps<{
    project: ProjectRecord;
    team: TeamAssignment[];
}>();

/**
 * Who the Project is for and when it runs, in one line.
 */
function summary(): string {
    const forWhom = props.project.client?.name ?? 'No Client';

    if (props.project.is_internal || !props.project.start_date) {
        return `${forWhom} · Always running`;
    }

    return `${forWhom} · ${formatPeriod(props.project.start_date, props.project.end_date)}`;
}

setLayoutProps({
    breadcrumbs: [
        { title: 'Projects', href: index() },
        { title: props.project.name, href: show(props.project.id) },
    ],
});
</script>

<template>
    <Head :title="project.name" />

    <PageContainer width="wide">
        <Heading :title="project.name" :description="summary()" />

        <section class="flex flex-col gap-3">
            <h2 class="text-base font-medium">Team</h2>
            <TeamTable
                :assignments="team"
                empty="Nobody you can see is assigned to this Project."
            />
        </section>
    </PageContainer>
</template>
