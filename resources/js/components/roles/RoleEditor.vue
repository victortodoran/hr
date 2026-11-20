<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Lock } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import RoleController from '@/actions/App/Http/Controllers/Access/RoleController';
import Callout from '@/components/Callout.vue';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import InputError from '@/components/InputError.vue';
import AddPeoplePopover from '@/components/roles/AddPeoplePopover.vue';
import DefaultRoleBadge from '@/components/roles/DefaultRoleBadge.vue';
import RoleHolderList from '@/components/roles/RoleHolderList.vue';
import ScopeControl from '@/components/roles/ScopeControl.vue';
import MoreMenu from '@/components/MoreMenu.vue';
import SavedTick from '@/components/SavedTick.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import UnsavedChangesDialog from '@/components/UnsavedChangesDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';
import { countOf, groupPermissions } from '@/lib/roles';
import type {
    Grants,
    PermissionChoice,
    RoleCandidate,
    RoleHolder,
    RoleRecord,
    Scope,
} from '@/types';

const props = defineProps<{
    role: RoleRecord | null;
    roles: RoleRecord[];
    permissions: PermissionChoice[];
    employees: RoleCandidate[];
    cancelTo: string;
}>();

const draftName = defineModel<string>('draftName', { default: '' });

const creating = computed(() => props.role === null);
const isAdmin = computed(() => props.role?.is_admin ?? false);

type RoleForm = {
    name: string;
    permissions: Grants;
    given_to_new_employees: boolean;
    holders: number[];
};

function snapshot(data: RoleForm): RoleForm {
    return {
        name: data.name,
        permissions: { ...data.permissions },
        given_to_new_employees: data.given_to_new_employees,
        holders: [...data.holders],
    };
}

const initial = ref<RoleForm>(
    snapshot({
        name: props.role?.name ?? '',
        permissions: props.role?.permissions ?? {},
        given_to_new_employees: props.role?.given_to_new_employees ?? false,
        holders: [],
    }),
);

const form = useForm<RoleForm>(snapshot(initial.value));

watch(
    () => form.name,
    (name) => (draftName.value = name),
    { immediate: true },
);

const takenName = computed(() => {
    const name = form.name.trim();

    return props.roles.some(
        (other) => other.id !== props.role?.id && other.name === name,
    )
        ? name
        : null;
});

const nameError = computed(() =>
    takenName.value !== null
        ? `A Role called ${takenName.value} already exists. Choose another name.`
        : form.errors.name,
);

const currentDefault = computed(() =>
    props.roles.find(
        (other) => other.given_to_new_employees && other.id !== props.role?.id,
    ),
);

const defaultHelp = computed(() => {
    if (isAdmin.value) {
        return 'Admin is never given to new Employees.';
    }

    return currentDefault.value
        ? `Only one Role has this: turning it on takes it off ${currentDefault.value.name}.`
        : 'Only one Role can have this.';
});

const copyFrom = ref('nothing');
const copyable = computed(() => props.roles.filter((other) => !other.is_admin));

watch(copyFrom, (id) => {
    const source = props.roles.find((other) => String(other.id) === id);
    form.permissions = { ...source?.permissions };
});

const groups = computed(() => groupPermissions(props.permissions));

const showAdminMatrix = ref(false);

function isGranted(permission: PermissionChoice): boolean {
    return isAdmin.value || permission.value in form.permissions;
}

function scopeOf(permission: PermissionChoice): Scope | 'none' {
    if (isAdmin.value) {
        return 'everyone';
    }

    return isGranted(permission)
        ? (form.permissions[permission.value] ?? 'none')
        : 'none';
}

function setScope(permission: PermissionChoice, scope: Scope | 'none'): void {
    if (scope === 'none') {
        delete form.permissions[permission.value];
    } else {
        form.permissions[permission.value] = scope;
    }
}

function setGranted(permission: PermissionChoice, granted: boolean): void {
    if (granted) {
        form.permissions[permission.value] = null;
    } else {
        delete form.permissions[permission.value];
    }
}

function isChanged(permission: PermissionChoice): boolean {
    return (
        initial.value.permissions[permission.value] !==
        form.permissions[permission.value]
    );
}

const grantedCount = computed(() =>
    isAdmin.value
        ? props.permissions.length
        : Object.keys(form.permissions).length,
);

function grantedIn(permissions: PermissionChoice[]): number {
    return permissions.filter(isGranted).length;
}

const people = computed(
    () =>
        new Map<number, RoleHolder>(
            props.employees.map((person) => [person.id, person]),
        ),
);

const newHolders = computed(() =>
    form.holders
        .map((id) => people.value.get(id))
        .filter((person): person is RoleHolder => person !== undefined),
);

const candidates = computed(() =>
    props.employees.filter((employee) => !form.holders.includes(employee.id)),
);

function addHolders(ids: number[]): void {
    form.holders = [...form.holders, ...ids];
}

function removeHolder(id: number): void {
    form.holders = form.holders.filter((holder) => holder !== id);
}

const changes = computed(() => {
    let count = 0;

    if (form.name !== initial.value.name) {
        count++;
    }

    if (form.given_to_new_employees !== initial.value.given_to_new_employees) {
        count++;
    }

    return (
        count + props.permissions.filter(isChanged).length + form.holders.length
    );
});

const dirty = computed(() => changes.value > 0);

const blocked = computed(
    () =>
        form.name.trim() === '' ||
        takenName.value !== null ||
        (!creating.value && !dirty.value),
);

const otherErrors = computed(() =>
    Object.entries(form.errors as Record<string, string>)
        .filter(([field]) => field !== 'name')
        .map(([, message]) => message),
);

function save(): void {
    form.transform(({ holders, ...data }) => ({
        ...data,
        name: data.name.trim(),
        ...(creating.value ? { holders } : {}),
    })).submit(
        props.role
            ? RoleController.update(props.role.id)
            : RoleController.store(),
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (!creating.value) {
                    form.name = form.name.trim();
                    initial.value = snapshot(form.data());
                }
            },
        },
    );
}

const guard = useUnsavedChanges(dirty);

function cancel(): void {
    if (creating.value) {
        guard.leave(props.cancelTo);

        return;
    }

    form.defaults(snapshot(initial.value));
    form.reset();
    form.clearErrors();
}

const discardTitle = computed(() =>
    creating.value ? 'Discard this new Role?' : 'Discard changes?',
);

const discardDescription = computed(() =>
    creating.value
        ? "The Role hasn't been saved yet. If you leave now, everything you entered will be lost."
        : `You have ${countOf(changes.value, 'unsaved change', 'unsaved changes')} to ${props.role?.name}. If you leave now, they'll be lost.`,
);

const deleteOpen = ref(false);
</script>

<template>
    <form class="flex min-w-0 flex-col gap-6" @submit.prevent="save">
        <header class="flex items-start justify-between gap-3">
            <div class="grid min-w-0 gap-1">
                <h2
                    class="flex flex-wrap items-center gap-2 text-section"
                    data-test="role-title"
                >
                    <span class="break-words">{{
                        role?.name ?? 'New Role'
                    }}</span>
                    <Lock
                        v-if="isAdmin"
                        class="size-4 text-subtle-foreground"
                        aria-label="Built in"
                    />
                    <DefaultRoleBadge v-if="role?.given_to_new_employees" />
                </h2>
                <p class="text-meta text-muted-foreground tabular-nums">
                    {{ grantedCount }} of
                    {{
                        countOf(permissions.length, 'Permission', 'Permissions')
                    }}
                    <template v-if="role">
                        · held by
                        {{ countOf(role.holders.length, 'person', 'people') }}
                    </template>
                    <SavedTick :shown="form.recentlySuccessful" class="ml-2" />
                </p>
            </div>
            <MoreMenu v-if="role && !isAdmin" :label="`More for ${role.name}`">
                <DropdownMenuItem
                    variant="destructive"
                    @select="deleteOpen = true"
                    >Delete Role…</DropdownMenuItem
                >
            </MoreMenu>
        </header>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid content-start gap-2">
                <Label for="role-name">Name</Label>
                <Input
                    id="role-name"
                    v-model="form.name"
                    :placeholder="creating ? 'e.g. Team Lead' : undefined"
                    :aria-invalid="nameError ? true : undefined"
                    :aria-describedby="
                        nameError ? 'role-name-error' : undefined
                    "
                    autocomplete="off"
                    class="max-sm:h-11"
                    required
                />
                <p
                    v-if="nameError"
                    id="role-name-error"
                    role="alert"
                    class="text-caption text-destructive"
                >
                    {{ nameError }}
                </p>
            </div>

            <div class="grid content-start gap-2">
                <Label for="role-default">New Employees get this Role</Label>
                <div class="flex min-h-9 items-center gap-3">
                    <Switch
                        id="role-default"
                        v-model="form.given_to_new_employees"
                        :disabled="isAdmin"
                        aria-describedby="role-default-help"
                    />
                    <p
                        id="role-default-help"
                        class="text-caption text-muted-foreground"
                    >
                        {{ defaultHelp }}
                    </p>
                </div>
            </div>

            <div v-if="creating" class="grid content-start gap-2">
                <Label for="role-copy">Copy Permissions from</Label>
                <Select v-model="copyFrom">
                    <SelectTrigger id="role-copy" class="w-full max-sm:h-11">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="nothing"
                            >Nothing (start blank)</SelectItem
                        >
                        <SelectItem
                            v-for="source in copyable"
                            :key="source.id"
                            :value="String(source.id)"
                            >{{ source.name }}</SelectItem
                        >
                    </SelectContent>
                </Select>
            </div>
        </div>

        <section aria-labelledby="role-permissions" class="flex flex-col gap-3">
            <h3 id="role-permissions" class="sr-only">Permissions</h3>

            <Callout v-if="isAdmin" tone="info">
                Admin always holds every Permission, with Scope Everyone.
                <template #action>
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        :aria-expanded="showAdminMatrix"
                        aria-controls="permission-matrix"
                        @click="showAdminMatrix = !showAdminMatrix"
                        >{{
                            showAdminMatrix
                                ? 'Hide Permissions'
                                : 'Show Permissions'
                        }}</Button
                    >
                </template>
            </Callout>

            <div
                v-if="!isAdmin || showAdminMatrix"
                id="permission-matrix"
                class="overflow-hidden rounded-lg border"
            >
                <div
                    class="flex justify-between border-b bg-muted/50 px-4 py-2 text-meta font-medium text-muted-foreground max-sm:hidden"
                    aria-hidden="true"
                >
                    <span>Permission</span>
                    <span>Scope</span>
                </div>
                <fieldset
                    v-for="group in groups"
                    :key="group.group"
                    :disabled="isAdmin"
                    class="border-b last:border-b-0"
                >
                    <legend class="contents">
                        <span
                            class="flex w-full items-center justify-between bg-muted/30 px-4 py-2 font-semibold"
                        >
                            {{ group.group }}
                            <span
                                class="text-meta font-normal text-muted-foreground tabular-nums"
                                >{{ grantedIn(group.permissions) }} of
                                {{ group.permissions.length }}</span
                            >
                        </span>
                    </legend>
                    <div
                        v-for="permission in group.permissions"
                        :key="permission.value"
                        class="flex gap-2 border-t px-4 py-2 sm:min-h-11 sm:flex-row sm:items-center sm:justify-between sm:gap-4"
                        :class="[
                            permission.scopes.length > 0
                                ? 'flex-col'
                                : 'min-h-11 items-center justify-between',
                            { 'bg-surface': isChanged(permission) },
                        ]"
                        data-test="permission-row"
                    >
                        <span class="flex min-w-0 items-center gap-2">
                            <span>{{ permission.label }}</span>
                            <Badge
                                v-if="isChanged(permission)"
                                variant="secondary"
                                >Changed</Badge
                            >
                        </span>
                        <ScopeControl
                            v-if="permission.scopes.length > 0"
                            :model-value="scopeOf(permission)"
                            :label="permission.label"
                            :allowed="permission.scopes"
                            :disabled="isAdmin"
                            @update:model-value="setScope(permission, $event)"
                        />
                        <Switch
                            v-else
                            :model-value="isGranted(permission)"
                            :disabled="isAdmin"
                            :aria-label="permission.label"
                            @update:model-value="
                                setGranted(permission, $event === true)
                            "
                        />
                    </div>
                </fieldset>
            </div>
        </section>

        <section
            v-if="creating"
            aria-labelledby="role-people"
            class="flex flex-col gap-3"
        >
            <header class="flex items-center justify-between gap-3">
                <h3 id="role-people" class="text-section">People</h3>
                <AddPeoplePopover
                    :role-name="form.name.trim() || 'the Role'"
                    :candidates="candidates"
                    @add="addHolders"
                />
            </header>
            <RoleHolderList
                :holders="newHolders"
                empty="Nobody yet. You can add Employees now or after saving the Role."
            >
                <template #actions="{ holder }">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="max-sm:h-11"
                        :aria-label="`Remove ${holder.name}`"
                        @click="removeHolder(holder.id)"
                        >Remove</Button
                    >
                </template>
            </RoleHolderList>
        </section>

        <div v-if="otherErrors.length > 0" role="alert" class="grid gap-1">
            <InputError
                v-for="message in otherErrors"
                :key="message"
                :message="message"
            />
        </div>

        <div
            v-if="creating || dirty"
            role="region"
            aria-label="Unsaved changes"
            data-slot="save-bar"
            class="sticky bottom-4 z-20 flex items-center gap-2 rounded-xl border bg-surface-raised py-2 pr-2 pl-4 shadow-overlay max-sm:fixed max-sm:inset-x-0 max-sm:bottom-0 max-sm:rounded-none max-sm:border-x-0 max-sm:border-b-0 max-sm:pb-[max(0.5rem,env(safe-area-inset-bottom))]"
        >
            <span class="mr-auto text-body tabular-nums" aria-live="polite"
                ><span class="font-semibold">{{
                    countOf(changes, 'unsaved change', 'unsaved changes')
                }}</span></span
            >
            <Button
                type="button"
                variant="secondary"
                class="max-sm:h-11"
                @click="cancel"
                >Cancel</Button
            >
            <SubmitButton
                :processing="form.processing"
                :disabled="blocked"
                class="max-sm:h-11"
                >{{ creating ? 'New Role' : 'Save changes' }}</SubmitButton
            >
        </div>
        <div
            v-if="creating || dirty"
            class="h-16 sm:hidden"
            aria-hidden="true"
        />

        <ConfirmDeleteDialog
            v-if="role && !isAdmin"
            v-model:open="deleteOpen"
            without-trigger
            :form="RoleController.destroy.form(role.id)"
            :title="`Delete ${role.name}`"
            :description="
                role.holders.length === 0
                    ? 'Nobody holds it.'
                    : `${countOf(role.holders.length, 'person holds', 'people hold')} it and will lose what it allows.`
            "
            confirm-label="Delete Role"
            :error-bag="`delete-role-${role.id}`"
        />

        <UnsavedChangesDialog
            :open="guard.pending.value !== null"
            :title="discardTitle"
            :description="discardDescription"
            @keep="guard.keep"
            @discard="guard.discard"
        />
    </form>
</template>
