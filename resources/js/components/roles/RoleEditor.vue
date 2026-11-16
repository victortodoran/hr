<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Search, UserPlus, Users } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import EmploymentStatusBadge from '@/components/employees/EmploymentStatusBadge.vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import ScopeControl from '@/components/roles/ScopeControl.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import UnsavedChangesDialog from '@/components/UnsavedChangesDialog.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import { getInitials } from '@/composables/useInitials';
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

/**
 * Edits a Role, or makes a new one, in place: its name and whether new
 * Employees get it, its Permissions and Scopes, and who holds it, all saved
 * together. Counts the unsaved changes and asks before leaving with any.
 */
const props = defineProps<{
    /** The Role being edited, or null when making a new one. */
    role: RoleRecord | null;
    roles: RoleRecord[];
    permissions: PermissionChoice[];
    /** Every Employee who can be given a Role. */
    employees: RoleCandidate[];
    /** Where Cancel leads. */
    cancelTo: string;
}>();

/** The name typed so far, which the Role list shows for a new Role. */
const draftName = defineModel<string>('draftName', { default: '' });

const creating = computed(() => props.role === null);
const isAdmin = computed(() => props.role?.is_admin ?? false);

const initial = {
    name: props.role?.name ?? '',
    permissions: { ...props.role?.permissions } as Grants,
    given_to_new_employees: props.role?.given_to_new_employees ?? false,
    holders: props.role?.holders.map((holder) => holder.id) ?? [],
};

const form = useForm<{
    name: string;
    permissions: Grants;
    given_to_new_employees: boolean;
    holders: number[];
}>({
    name: initial.name,
    permissions: { ...initial.permissions },
    given_to_new_employees: initial.given_to_new_employees,
    holders: [...initial.holders],
});

watch(
    () => form.name,
    (name) => (draftName.value = name),
    { immediate: true },
);

/* Details */

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

/** The Role new Employees get now, when it's another one. */
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
        ? `Only one Role can have this. Turning it on takes it off ${currentDefault.value.name}.`
        : 'Only one Role can have this.';
});

const copyFrom = ref('nothing');
const copyable = computed(() => props.roles.filter((other) => !other.is_admin));

watch(copyFrom, (id) => {
    const source = props.roles.find((other) => String(other.id) === id);
    form.permissions = { ...source?.permissions };
});

/* Permissions */

const groups = computed(() => groupPermissions(props.permissions));

function isGranted(permission: PermissionChoice): boolean {
    return isAdmin.value || permission.value in form.permissions;
}

function scopeOf(permission: PermissionChoice): Scope | null {
    if (permission.scopes.length === 0) {
        return null;
    }

    return isAdmin.value
        ? 'everyone'
        : (form.permissions[permission.value] ?? null);
}

function toggle(permission: PermissionChoice, granted: boolean): void {
    if (granted) {
        form.permissions[permission.value] = permission.scopes[0] ?? null;
    } else {
        delete form.permissions[permission.value];
    }
}

function setScope(permission: PermissionChoice, scope: Scope | null): void {
    if (scope !== null && isGranted(permission)) {
        form.permissions[permission.value] = scope;
    }
}

function isChanged(permission: PermissionChoice): boolean {
    return (
        initial.permissions[permission.value] !==
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

/* People */

const people = computed(
    () =>
        new Map<number, RoleHolder>(
            [...(props.role?.holders ?? []), ...props.employees].map(
                (person) => [person.id, person],
            ),
        ),
);

const holders = computed(() =>
    form.holders
        .map((id) => people.value.get(id))
        .filter((person): person is RoleHolder => person !== undefined),
);

const personSearch = ref('');

const matches = computed(() => {
    const query = personSearch.value.trim().toLocaleLowerCase();

    if (query === '') {
        return [];
    }

    return props.employees
        .filter(
            (employee) =>
                !form.holders.includes(employee.id) &&
                employee.name.toLocaleLowerCase().includes(query),
        )
        .slice(0, 8);
});

function addHolder(id: number): void {
    form.holders = [...form.holders, id];
    personSearch.value = '';
}

/** Enter adds the top match, as in a combobox, instead of saving the Role. */
function addTopMatch(): void {
    const top = matches.value[0];

    if (top) {
        addHolder(top.id);
    }
}

function removeHolder(id: number): void {
    form.holders = form.holders.filter((holder) => holder !== id);
}

/* Saving */

const changes = computed(() => {
    let count = 0;

    if (form.name !== initial.name) {
        count++;
    }

    if (form.given_to_new_employees !== initial.given_to_new_employees) {
        count++;
    }

    count += props.permissions.filter(isChanged).length;
    count += form.holders.filter((id) => !initial.holders.includes(id)).length;
    count += initial.holders.filter((id) => !form.holders.includes(id)).length;

    return count;
});

const dirty = computed(() => changes.value > 0);

/**
 * Whether anyone was added or removed. Holders are only sent then, so a
 * save that leaves People alone never undoes someone else's changes to it.
 */
const peopleChanged = computed(
    () =>
        form.holders.length !== initial.holders.length ||
        form.holders.some((id) => !initial.holders.includes(id)),
);

const blocked = computed(
    () =>
        form.name.trim() === '' ||
        takenName.value !== null ||
        (!creating.value && !dirty.value),
);

/** Refusals the server gave that no field shows, such as leaving nobody able to manage Roles. */
const otherErrors = computed(() =>
    Object.entries(form.errors as Record<string, string>)
        .filter(([field]) => field !== 'name')
        .map(([, message]) => message),
);

function save(): void {
    form.transform(({ holders, ...data }) => ({
        ...data,
        name: data.name.trim(),
        ...(peopleChanged.value ? { holders } : {}),
    })).submit(
        props.role
            ? RoleController.update(props.role.id)
            : RoleController.store(),
        { preserveScroll: true },
    );
}

const guard = useUnsavedChanges(dirty);

const discardTitle = computed(() =>
    creating.value ? 'Discard this new Role?' : 'Discard changes?',
);

const discardDescription = computed(() =>
    creating.value
        ? "The Role hasn't been created yet. If you leave now, everything you entered will be lost."
        : `You have ${countOf(changes.value, 'unsaved change', 'unsaved changes')} to ${props.role?.name}. If you leave now, they'll be lost.`,
);
</script>

<template>
    <form class="grid gap-4" @submit.prevent="save">
        <h2 class="text-xl font-semibold">
            {{ creating ? 'New Role' : `Edit ${role?.name}` }}
        </h2>

        <Card>
            <CardHeader>
                <CardTitle>Details</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-5">
                <div class="grid gap-2">
                    <Label for="role-name">Name</Label>
                    <Input
                        id="role-name"
                        v-model="form.name"
                        :placeholder="creating ? 'e.g. Team Lead' : undefined"
                        :aria-invalid="nameError ? true : undefined"
                        :aria-describedby="
                            nameError
                                ? 'role-name-error'
                                : creating
                                  ? 'role-name-hint'
                                  : undefined
                        "
                        autocomplete="off"
                        class="h-11"
                        required
                    />
                    <p
                        v-if="nameError"
                        id="role-name-error"
                        role="alert"
                        class="text-sm text-destructive"
                    >
                        {{ nameError }}
                    </p>
                    <p
                        v-else-if="creating"
                        id="role-name-hint"
                        class="text-sm text-muted-foreground"
                    >
                        Must be different from existing Roles.
                    </p>
                </div>

                <div class="flex items-start justify-between gap-4">
                    <div class="grid gap-1">
                        <Label for="role-default">Give to new Employees</Label>
                        <p
                            id="role-default-help"
                            class="text-sm text-muted-foreground"
                        >
                            {{ defaultHelp }}
                        </p>
                    </div>
                    <Switch
                        id="role-default"
                        v-model="form.given_to_new_employees"
                        :disabled="isAdmin"
                        aria-describedby="role-default-help"
                        class="mt-1"
                    />
                </div>

                <div v-if="creating" class="grid gap-2">
                    <Label for="role-copy">Copy permissions from</Label>
                    <Select v-model="copyFrom">
                        <SelectTrigger
                            id="role-copy"
                            class="h-11 w-full sm:w-72"
                            aria-describedby="role-copy-hint"
                        >
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
                    <p
                        id="role-copy-hint"
                        class="text-sm text-muted-foreground"
                    >
                        Copies permissions and Scopes, not people.
                    </p>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-baseline justify-between gap-2">
                    Permissions
                    <span class="text-sm font-normal text-muted-foreground"
                        >{{ grantedCount }} of {{ permissions.length }}</span
                    >
                </CardTitle>
                <CardDescription v-if="isAdmin"
                    >Admin always holds every Permission, with Scope
                    Everyone.</CardDescription
                >
            </CardHeader>
            <CardContent class="grid gap-5">
                <fieldset
                    v-for="group in groups"
                    :key="group.group"
                    class="grid gap-1"
                    :disabled="isAdmin"
                >
                    <legend
                        class="mb-1 flex w-full justify-between text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        {{ group.group }}
                        <span class="normal-case"
                            >{{ grantedIn(group.permissions) }} of
                            {{ group.permissions.length }}</span
                        >
                    </legend>
                    <div
                        v-for="permission in group.permissions"
                        :key="permission.value"
                        class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 rounded-md py-1"
                        data-test="permission-row"
                    >
                        <Label
                            class="flex min-h-11 min-w-0 flex-1 cursor-pointer items-center gap-3 font-normal"
                        >
                            <Checkbox
                                :model-value="isGranted(permission)"
                                :disabled="isAdmin"
                                @update:model-value="
                                    toggle(permission, $event === true)
                                "
                            />
                            <span>{{ permission.label }}</span>
                            <span
                                v-if="isChanged(permission)"
                                class="size-2 shrink-0 rounded-full bg-blue-600"
                                role="img"
                                aria-label="Changed"
                            />
                        </Label>
                        <ScopeControl
                            v-if="permission.scopes.length > 0"
                            :model-value="scopeOf(permission)"
                            :label="permission.label"
                            :allowed="permission.scopes"
                            :disabled="isAdmin || !isGranted(permission)"
                            @update:model-value="setScope(permission, $event)"
                        />
                    </div>
                </fieldset>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>People</CardTitle>
                <CardDescription>Who will hold the Role</CardDescription>
            </CardHeader>
            <CardContent class="grid gap-3">
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <Input
                        v-model="personSearch"
                        type="search"
                        placeholder="Add an Employee…"
                        aria-label="Add an Employee"
                        autocomplete="off"
                        @keydown.enter.prevent="addTopMatch"
                        class="h-11 pl-9"
                    />
                </div>
                <ul
                    v-if="personSearch.trim() !== ''"
                    class="grid gap-1 rounded-md border p-1"
                    aria-label="Employees to add"
                >
                    <li v-for="match in matches" :key="match.id">
                        <button
                            type="button"
                            class="flex min-h-11 w-full items-center gap-3 rounded-sm px-2 text-left text-sm hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            @click="addHolder(match.id)"
                        >
                            <UserPlus
                                class="size-4 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <span class="flex-1 truncate">{{
                                match.name
                            }}</span>
                            <EmploymentStatusBadge
                                v-if="match.status !== 'current'"
                                :status="match.status"
                            />
                        </button>
                    </li>
                    <li
                        v-if="matches.length === 0"
                        class="px-2 py-2 text-sm text-muted-foreground"
                    >
                        Nobody else matches that search.
                    </li>
                </ul>

                <EmptyState
                    v-if="holders.length === 0"
                    :icon="Users"
                    :message="
                        creating
                            ? 'Nobody yet. You can add Employees now or after creating the Role.'
                            : 'Nobody will hold this Role.'
                    "
                />
                <ul v-else class="grid divide-y">
                    <li
                        v-for="holder in holders"
                        :key="holder.id"
                        class="flex flex-wrap items-center gap-3 py-2"
                    >
                        <Avatar class="size-8">
                            <AvatarFallback class="text-xs">{{
                                getInitials(holder.name)
                            }}</AvatarFallback>
                        </Avatar>
                        <span
                            class="min-w-0 flex-1 truncate text-sm font-medium"
                            >{{ holder.name }}</span
                        >
                        <Badge
                            v-if="!initial.holders.includes(holder.id)"
                            variant="secondary"
                            >Will be added</Badge
                        >
                        <EmploymentStatusBadge
                            v-if="holder.status !== 'current'"
                            :status="holder.status"
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            class="min-h-11"
                            :aria-label="`Take ${form.name.trim() || 'the Role'} away from ${holder.name}`"
                            @click="removeHolder(holder.id)"
                            >Remove</Button
                        >
                    </li>
                </ul>
            </CardContent>
        </Card>

        <div
            v-if="otherErrors.length > 0"
            role="alert"
            class="grid gap-1 rounded-md border border-destructive/50 bg-destructive/5 px-3 py-2"
        >
            <InputError
                v-for="message in otherErrors"
                :key="message"
                :message="message"
            />
        </div>

        <div
            class="sticky bottom-0 z-10 -mx-1 flex flex-wrap items-center justify-between gap-3 border-t bg-background px-1 py-3"
        >
            <div class="grid gap-0.5 text-sm">
                <span class="font-medium" aria-live="polite">{{
                    countOf(changes, 'unsaved change', 'unsaved changes')
                }}</span>
                <span
                    v-if="creating && form.name.trim() === ''"
                    class="text-muted-foreground"
                    >Give the Role a name to create it.</span
                >
                <span
                    v-else-if="creating && grantedCount === 0"
                    class="text-muted-foreground"
                    >No permissions ticked. You can still create it and add them
                    later.</span
                >
            </div>
            <div class="flex flex-wrap gap-2">
                <Button
                    type="button"
                    variant="secondary"
                    class="min-h-11"
                    @click="guard.leave(cancelTo)"
                    >Cancel</Button
                >
                <SubmitButton
                    :processing="form.processing"
                    :disabled="blocked"
                    class="min-h-11"
                    >{{
                        creating ? 'Create Role' : 'Save changes'
                    }}</SubmitButton
                >
            </div>
        </div>

        <UnsavedChangesDialog
            :open="guard.pending.value !== null"
            :title="discardTitle"
            :description="discardDescription"
            @keep="guard.keep"
            @discard="guard.discard"
        />
    </form>
</template>
