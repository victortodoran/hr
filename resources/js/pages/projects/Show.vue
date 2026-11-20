<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import { computed, ref } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/Projects/ProjectController';
import TeamTable from '@/components/assignments/TeamTable.vue';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import PageContainer from '@/components/PageContainer.vue';
import MoreMenu from '@/components/MoreMenu.vue';
import PageHeader from '@/components/PageHeader.vue';
import ProjectSheet from '@/components/projects/ProjectSheet.vue';
import StatStrip from '@/components/StatStrip.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { formatPeriod } from '@/lib/dates';
import { deleteProjectDescription } from '@/lib/projects';
import { index, show } from '@/routes/projects';
import type { Choice, ProjectRecord, TeamAssignment, TeamToday } from '@/types';

const props = defineProps<{
    project: ProjectRecord;
    team: TeamAssignment[];
    teamToday: TeamToday;
    canManage: boolean;
    clients: Choice[];
}>();

const meta = computed(() => {
    if (props.project.is_internal || !props.project.start_date) {
        return 'Always running';
    }

    const dates = formatPeriod(
        props.project.start_date,
        props.project.end_date,
    );

    return props.project.client
        ? `${props.project.client.name} · ${dates}`
        : dates;
});

const stats = computed(() => [
    {
        label: 'Employees assigned today',
        value: String(props.teamToday.employees),
    },
    {
        label: 'Capacity across the team today',
        value: `${props.teamToday.capacity}%`,
    },
]);

const editOpen = ref(false);
const deleteOpen = ref(false);

setLayoutProps({
    breadcrumbs: [
        { title: 'Projects', href: index() },
        { title: props.project.name, href: show(props.project.id) },
    ],
});
</script>

<template>
    <Head :title="project.name" />

    <PageContainer width="full">
        <PageHeader :title="project.name" :description="meta">
            <template v-if="project.is_internal" #status>
                <Badge variant="secondary">Internal Project</Badge>
            </template>
            <template v-if="canManage" #actions>
                <Button variant="secondary" @click="editOpen = true"
                    ><Pencil />Edit</Button
                >
                <MoreMenu label="More for this Project">
                    <DropdownMenuItem
                        variant="destructive"
                        @select="deleteOpen = true"
                        >Delete Project…</DropdownMenuItem
                    >
                </MoreMenu>
            </template>
        </PageHeader>

        <div class="flex flex-col gap-1.5">
            <StatStrip :stats="stats" />
            <p class="text-caption text-muted-foreground">
                Counts only the Assignments you can see.
            </p>
        </div>

        <section class="flex flex-col gap-3">
            <h2 class="text-section">Team</h2>
            <TeamTable
                :assignments="team"
                empty="Nobody you can see is assigned to this Project."
            />
        </section>

        <template v-if="canManage">
            <ConfirmDeleteDialog
                v-model:open="deleteOpen"
                without-trigger
                :form="ProjectController.destroy.form(project.id)"
                :title="`Delete ${project.name}`"
                :description="deleteProjectDescription"
                confirm-label="Delete Project"
            />

            <ProjectSheet
                v-model:open="editOpen"
                :project="project"
                :clients="clients"
            />
        </template>
    </PageContainer>
</template>
