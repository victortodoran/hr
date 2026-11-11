<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import DeleteRole from '@/components/roles/DeleteRole.vue';
import RoleForm from '@/components/roles/RoleForm.vue';
import RoleHolders from '@/components/roles/RoleHolders.vue';
import { Badge } from '@/components/ui/badge';
import { index } from '@/routes/roles';
import type { Choice, PermissionChoice, RoleRecord } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Roles', href: index() }],
    },
});

const props = defineProps<{
    roles: RoleRecord[];
    permissions: PermissionChoice[];
    employees: Choice[];
}>();

function labelOf(permission: string): string {
    return (
        props.permissions.find((choice) => choice.value === permission)
            ?.label ?? permission
    );
}
</script>

<template>
    <Head title="Roles" />

    <div class="flex max-w-3xl flex-col gap-8 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                title="Roles"
                description="What each Role allows, and who holds it"
            />
            <RoleForm :permissions="permissions" />
        </div>

        <section
            v-for="role in roles"
            :key="role.id"
            class="grid gap-4 rounded-lg border p-4"
        >
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <h2 class="font-medium">{{ role.name }}</h2>
                    <Badge v-if="role.given_to_new_employees" variant="outline"
                        >Given to new Employees</Badge
                    >
                </div>
                <div class="flex gap-2">
                    <RoleForm
                        :key="JSON.stringify(role)"
                        :role="role"
                        :permissions="permissions"
                    />
                    <DeleteRole :role="role" />
                </div>
            </div>

            <ul
                v-if="Object.keys(role.permissions).length > 0"
                class="flex flex-wrap gap-2"
            >
                <li
                    v-for="(scope, permission) in role.permissions"
                    :key="permission"
                >
                    <Badge variant="secondary">
                        {{ labelOf(permission) }}
                        <span v-if="scope" class="text-muted-foreground"
                            >· {{ scope }}</span
                        >
                    </Badge>
                </li>
            </ul>
            <p v-else class="text-sm text-muted-foreground">No Permissions.</p>

            <RoleHolders :role="role" :employees="employees" />
        </section>
    </div>
</template>
