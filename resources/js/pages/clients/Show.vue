<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import { computed, ref } from 'vue';
import TeamTable from '@/components/assignments/TeamTable.vue';
import MoreMenu from '@/components/MoreMenu.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import ClientDialogs from '@/components/projects/ClientDialogs.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { formatPeriod } from '@/lib/dates';
import { index, show } from '@/routes/clients';
import { show as showProject } from '@/routes/projects';
import type { Choice, ClientProject, TeamAssignment } from '@/types';

const props = defineProps<{
    client: Choice;
    projects: ClientProject[];
}>();

const record = computed(() => ({
    ...props.client,
    projects_count: props.projects.length,
}));

const renameOpen = ref(false);
const deleteOpen = ref(false);

type ProjectAssignment = TeamAssignment & { project_id: number };

const assignments = computed<ProjectAssignment[]>(() =>
    props.projects.flatMap((project) =>
        project.team.map((member) => ({ ...member, project_id: project.id })),
    ),
);

const groups = computed(() =>
    props.projects.map((project) => String(project.id)),
);

/** The Project a group row stands for, as a list of one to loop over. */
function projectsOf(group: string): ClientProject[] {
    return props.projects.filter((project) => String(project.id) === group);
}

setLayoutProps({
    breadcrumbs: [
        { title: 'Clients', href: index() },
        { title: props.client.name, href: show(props.client.id) },
    ],
});
</script>

<template>
    <Head :title="client.name" />

    <PageContainer width="full">
        <PageHeader
            :title="client.name"
            description="This Client's Projects, and the Assignments you can see on each today."
        >
            <template #actions>
                <Button variant="secondary" @click="renameOpen = true"
                    ><Pencil />Rename…</Button
                >
                <MoreMenu label="More for this Client">
                    <DropdownMenuItem
                        variant="destructive"
                        @select="deleteOpen = true"
                        >Delete Client…</DropdownMenuItem
                    >
                </MoreMenu>
            </template>
        </PageHeader>

        <TeamTable
            :assignments="assignments"
            :group-by="(row) => String(row.project_id)"
            :groups="groups"
            empty="The Projects done for this Client appear here. It has none yet."
        >
            <template #group="{ group, rows }">
                <div
                    v-for="project in projectsOf(group)"
                    :key="project.id"
                    class="flex flex-wrap items-baseline gap-x-3 gap-y-0.5"
                >
                    <Link
                        :href="showProject(project.id)"
                        class="font-medium text-foreground underline-offset-4 hover:underline"
                        >{{ project.name }}</Link
                    >
                    <span class="text-muted-foreground tabular-nums">{{
                        formatPeriod(project.start_date, project.end_date)
                    }}</span>
                    <span
                        v-if="rows.length === 0"
                        class="text-muted-foreground"
                        data-test="no-one-assigned"
                        >· No one you can see is assigned today</span
                    >
                </div>
            </template>
        </TeamTable>

        <ClientDialogs
            v-model:rename-open="renameOpen"
            v-model:delete-open="deleteOpen"
            :client="record"
        />
    </PageContainer>
</template>
