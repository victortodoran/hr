<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import TeamTable from '@/components/assignments/TeamTable.vue';
import Heading from '@/components/Heading.vue';
import { index, show } from '@/routes/clients';
import type { Choice, TeamAssignment } from '@/types';

const props = defineProps<{
    client: Choice;
    assignments: TeamAssignment[];
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

    <div class="flex max-w-4xl flex-col gap-6 p-4">
        <Heading
            :title="client.name"
            description="The Employees assigned to its Projects"
        />

        <TeamTable
            :assignments="assignments"
            show-project
            empty="Nobody you can see is assigned to this Client's Projects."
        />
    </div>
</template>
