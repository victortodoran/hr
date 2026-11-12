<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import ClientController from '@/actions/App/Http/Controllers/ClientController';
import DeleteClient from '@/components/clients/DeleteClient.vue';
import RenameClient from '@/components/clients/RenameClient.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, show } from '@/routes/clients';
import type { ClientRecord } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Clients', href: index() }],
    },
});

defineProps<{
    clients: ClientRecord[];
}>();
</script>

<template>
    <Head title="Clients" />

    <div class="flex max-w-2xl flex-col gap-8 p-4">
        <Heading
            title="Clients"
            description="The outside organisations the company does Projects for"
        />

        <Form
            v-bind="ClientController.store.form()"
            reset-on-success
            :options="{ preserveScroll: true }"
            class="flex items-end gap-2"
            v-slot="{ errors, processing }"
        >
            <div class="grid flex-1 gap-2">
                <Label for="new-client">New Client</Label>
                <Input
                    id="new-client"
                    name="name"
                    placeholder="e.g. Globex"
                    required
                />
                <InputError :message="errors.name" />
            </div>
            <SubmitButton :processing="processing">Add</SubmitButton>
        </Form>

        <ul class="divide-y rounded-lg border">
            <li
                v-for="client in clients"
                :key="client.id"
                class="flex items-center justify-between gap-4 px-4 py-3"
            >
                <div>
                    <Link
                        :href="show(client.id)"
                        class="font-medium underline-offset-4 hover:underline"
                        >{{ client.name }}</Link
                    >
                    <p class="text-sm text-muted-foreground">
                        {{ client.projects_count }}
                        {{
                            client.projects_count === 1 ? 'Project' : 'Projects'
                        }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <RenameClient :client="client" />
                    <DeleteClient :client="client" />
                </div>
            </li>
            <li
                v-if="clients.length === 0"
                class="px-4 py-6 text-center text-sm text-muted-foreground"
            >
                No Clients yet. You only need them for work done for outside
                organisations.
            </li>
        </ul>
    </div>
</template>
