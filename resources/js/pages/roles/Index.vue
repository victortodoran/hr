<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { ShieldCheck } from '@lucide/vue';
import { computed, ref, watch, watchEffect } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import RoleEditor from '@/components/roles/RoleEditor.vue';
import RoleList from '@/components/roles/RoleList.vue';
import RolePeople from '@/components/roles/RolePeople.vue';
import { Button } from '@/components/ui/button';
import CompanySettingsLayout from '@/layouts/settings/CompanySettingsLayout.vue';
import { create, index, show } from '@/routes/roles';
import type {
    BreadcrumbItem,
    PermissionChoice,
    RoleCandidate,
    RoleRecord,
    RolesPageMode,
} from '@/types';

const props = defineProps<{
    mode: RolesPageMode;
    selected: number | null;
    roles: RoleRecord[];
    permissions: PermissionChoice[];
    employees: RoleCandidate[];
}>();

const selectedRole = computed(
    () => props.roles.find((role) => role.id === props.selected) ?? null,
);

const draftName = ref('');

const lastSelected = ref<number | null>(props.selected);

watch(
    () => props.selected,
    (id) => {
        if (id !== null) {
            lastSelected.value = id;
        }
    },
);

const cancelNewTo = computed(() =>
    props.roles.some((role) => role.id === lastSelected.value)
        ? show(lastSelected.value!).url
        : index().url,
);

function permissionCount(role: RoleRecord): number {
    return role.is_admin
        ? props.permissions.length
        : Object.keys(role.permissions).length;
}

const title = computed(() =>
    props.mode === 'create' ? 'New Role' : (selectedRole.value?.name ?? null),
);

watchEffect(() => {
    const breadcrumbs: BreadcrumbItem[] = [{ title: 'Roles', href: index() }];

    if (props.mode === 'create') {
        breadcrumbs.push({ title: 'New Role', href: create() });
    } else if (selectedRole.value) {
        breadcrumbs.push({
            title: selectedRole.value.name,
            href: show(selectedRole.value.id),
        });
    }

    setLayoutProps({ breadcrumbs });
});
</script>

<template>
    <Head :title="title === null ? 'Roles' : `${title} · Roles`" />

    <CompanySettingsLayout>
        <div
            class="grid items-start gap-6 lg:grid-cols-[15rem_minmax(0,1fr)] lg:gap-8"
        >
            <RoleList
                class="lg:sticky lg:top-4"
                :roles="roles"
                :selected="selected"
                :mode="mode"
                :permission-count="permissionCount"
                :new-name="draftName"
            />

            <section
                class="flex min-w-0 flex-col gap-8"
                aria-label="Selected Role"
            >
                <RoleEditor
                    v-if="mode === 'create'"
                    key="create"
                    v-model:draft-name="draftName"
                    :role="null"
                    :roles="roles"
                    :permissions="permissions"
                    :employees="employees"
                    :cancel-to="cancelNewTo"
                />
                <template v-else-if="selectedRole">
                    <RoleEditor
                        :key="selectedRole.id"
                        :role="selectedRole"
                        :roles="roles"
                        :permissions="permissions"
                        :employees="employees"
                        :cancel-to="show(selectedRole.id).url"
                    />
                    <RolePeople
                        :key="`people-${selectedRole.id}`"
                        :role="selectedRole"
                        :employees="employees"
                    />
                </template>
                <EmptyState
                    v-else
                    :icon="ShieldCheck"
                    message="Roles say what the Employees holding them may do. There are none yet."
                >
                    <template #action>
                        <Button variant="secondary" as-child>
                            <Link :href="create()">New Role</Link>
                        </Button>
                    </template>
                </EmptyState>
            </section>
        </div>
    </CompanySettingsLayout>
</template>
