<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ShieldCheck } from '@lucide/vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import PageContainer from '@/components/PageContainer.vue';
import DeleteRole from '@/components/roles/DeleteRole.vue';
import RoleForm from '@/components/roles/RoleForm.vue';
import RoleHolders from '@/components/roles/RoleHolders.vue';
import { Badge } from '@/components/ui/badge';
import { index } from '@/routes/roles';
import type {
    Choice,
    PermissionChoice,
    RoleRecord,
    TableColumn,
} from '@/types';

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

const columns: TableColumn[] = [
    { key: 'role', label: 'Role' },
    { key: 'permissions', label: 'Permissions', minor: true },
    { key: 'holders', label: 'Held by' },
];

function labelOf(permission: string): string {
    return (
        props.permissions.find((choice) => choice.value === permission)
            ?.label ?? permission
    );
}
</script>

<template>
    <Head title="Roles" />

    <PageContainer width="wide">
        <div class="flex items-start justify-between gap-4">
            <Heading
                title="Roles"
                description="What each Role allows, and who holds it"
            />
            <RoleForm :permissions="permissions" />
        </div>

        <DataTable :columns="columns" :rows="roles">
            <template #cell-role="{ row }">
                <div class="grid gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium">{{ row.name }}</span>
                        <Badge
                            v-if="row.given_to_new_employees"
                            variant="outline"
                            >Given to new Employees</Badge
                        >
                    </div>
                    <div class="flex gap-2">
                        <RoleForm
                            :key="JSON.stringify(row)"
                            :role="row"
                            :permissions="permissions"
                        />
                        <DeleteRole v-if="!row.is_admin" :role="row" />
                    </div>
                </div>
            </template>
            <template #cell-permissions="{ row }">
                <div class="grid gap-2">
                    <p v-if="row.is_admin" class="text-muted-foreground">
                        Admin always holds every Permission, with Scope
                        everyone.
                    </p>
                    <ul
                        v-if="Object.keys(row.permissions).length > 0"
                        class="flex flex-wrap gap-2"
                    >
                        <li
                            v-for="(scope, permission) in row.permissions"
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
                    <p v-else class="text-muted-foreground">No Permissions.</p>
                </div>
            </template>
            <template #cell-holders="{ row }">
                <RoleHolders :role="row" :employees="employees" />
            </template>
            <template #empty>
                <EmptyState
                    :icon="ShieldCheck"
                    message="Roles say what the Employees holding them may do. There are none yet."
                >
                    <template #action>
                        <RoleForm :permissions="permissions" />
                    </template>
                </EmptyState>
            </template>
        </DataTable>
    </PageContainer>
</template>
