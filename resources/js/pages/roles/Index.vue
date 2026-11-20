<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { Plus, ShieldCheck } from '@lucide/vue';
import { computed, ref, watch, watchEffect } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import SettingsFrame from '@/components/SettingsFrame.vue';
import { useCompanySettingsLinks } from '@/composables/useCompanySettingsLinks';
import PageContainer from '@/components/PageContainer.vue';
import RoleEditor from '@/components/roles/RoleEditor.vue';
import RoleList from '@/components/roles/RoleList.vue';
import RoleView from '@/components/roles/RoleView.vue';
import { Button } from '@/components/ui/button';
import { create, edit, index, show } from '@/routes/roles';
import type {
    BreadcrumbItem,
    PermissionChoice,
    RoleCandidate,
    RoleRecord,
    RolesPageMode,
} from '@/types';

/**
 * The Roles page: every Role in a list on the left, and on the right the
 * selected Role, either as it stands or being edited, or a new Role being
 * made. Which Role and what is being done to it live in the URL.
 */
const props = defineProps<{
    mode: RolesPageMode;
    /** The selected Role's ID; null when making a new one or there are none. */
    selected: number | null;
    roles: RoleRecord[];
    permissions: PermissionChoice[];
    /** Every Employee who can be given a Role: current and Future ones. */
    employees: RoleCandidate[];
}>();

const selectedRole = computed(
    () => props.roles.find((role) => role.id === props.selected) ?? null,
);

const draftName = ref('');

/** The Role last shown, which Cancel on a new Role goes back to. */
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

const title = computed(() => {
    if (props.mode === 'create') {
        return 'New Role';
    }

    if (props.mode === 'edit' && selectedRole.value) {
        return `Edit ${selectedRole.value.name}`;
    }

    return selectedRole.value?.name ?? 'Roles';
});

watchEffect(() => {
    const breadcrumbs: BreadcrumbItem[] = [{ title: 'Roles', href: index() }];

    if (props.mode === 'create') {
        breadcrumbs.push({ title: 'New Role', href: create() });
    } else if (selectedRole.value) {
        breadcrumbs.push({
            title: title.value,
            href: (props.mode === 'edit' ? edit : show)(selectedRole.value.id),
        });
    }

    setLayoutProps({ breadcrumbs });
});

const settingsLinks = useCompanySettingsLinks();
</script>

<template>
    <Head :title="title === 'Roles' ? 'Roles' : `${title} · Roles`" />

    <PageContainer width="full">
        <PageHeader
            title="Roles"
            description="What each Role allows, and who holds it"
        >
            <template v-if="mode !== 'create'" #actions>
                <Button class="min-h-11" as-child>
                    <Link :href="create()" preserve-state preserve-scroll>
                        <Plus class="size-4" aria-hidden="true" />
                        New Role
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <SettingsFrame :items="settingsLinks" label="Company settings">
            <div class="flex flex-col gap-8">
                <div
                    class="grid items-start gap-6 lg:grid-cols-[18rem_minmax(0,1fr)]"
                >
                    <RoleList
                        class="lg:sticky lg:top-4"
                        :roles="roles"
                        :selected="selected"
                        :mode="mode"
                        :permission-count="permissionCount"
                        :new-name="draftName"
                    />

                    <section class="min-w-0" aria-label="Selected Role">
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
                        <RoleEditor
                            v-else-if="mode === 'edit' && selectedRole"
                            :key="`edit-${selectedRole.id}`"
                            :role="selectedRole"
                            :roles="roles"
                            :permissions="permissions"
                            :employees="employees"
                            :cancel-to="show(selectedRole.id).url"
                        />
                        <RoleView
                            v-else-if="selectedRole"
                            :key="selectedRole.id"
                            :role="selectedRole"
                            :permissions="permissions"
                            :employees="employees"
                        />
                        <EmptyState
                            v-else
                            :icon="ShieldCheck"
                            message="Roles say what the Employees holding them may do. There are none yet."
                        />
                    </section>
                </div>
            </div>
        </SettingsFrame>
    </PageContainer>
</template>
