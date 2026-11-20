<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { FolderKanban } from '@lucide/vue';
import TeamTable from '@/components/assignments/TeamTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import PageContainer from '@/components/PageContainer.vue';
import { formatPeriod } from '@/lib/dates';
import { index, show } from '@/routes/clients';
import { show as showProject } from '@/routes/projects';
import type { Choice, ClientProject } from '@/types';

const props = defineProps<{
    client: Choice;
    projects: ClientProject[];
}>();

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
            description="Its Projects, and who is assigned to each today"
        />

        <section
            v-for="project in projects"
            :key="project.id"
            class="flex flex-col gap-3"
        >
            <div class="flex items-baseline justify-between gap-4">
                <h2 class="font-medium">
                    <Link
                        :href="showProject(project.id)"
                        class="underline-offset-4 hover:underline"
                        >{{ project.name }}</Link
                    >
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ formatPeriod(project.start_date, project.end_date) }}
                </p>
            </div>
            <TeamTable
                :assignments="project.team"
                empty="Nobody you can see is assigned to this Project today."
            />
        </section>

        <div v-if="projects.length === 0" class="rounded-lg border">
            <EmptyState
                :icon="FolderKanban"
                message="The Projects done for this Client appear here. It has none yet."
            />
        </div>
    </PageContainer>
</template>
