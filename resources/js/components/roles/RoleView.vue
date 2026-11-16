<script setup lang="ts">
import { Form, Link, useForm } from '@inertiajs/vue3';
import { Info, Lock, Search, Trash2, Users } from '@lucide/vue';
import { computed, ref } from 'vue';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import RoleHolderController from '@/actions/App/Http/Controllers/RoleHolderController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import EmploymentStatusBadge from '@/components/employees/EmploymentStatusBadge.vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import AddPeoplePopover from '@/components/roles/AddPeoplePopover.vue';
import ScopeBadge from '@/components/roles/ScopeBadge.vue';
import SubmitButton from '@/components/SubmitButton.vue';
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
import { Input } from '@/components/ui/input';
import { getInitials } from '@/composables/useInitials';
import { countOf, groupPermissions, scopes } from '@/lib/roles';
import { show as employeePage } from '@/routes/employees';
import { edit } from '@/routes/roles';
import type {
    PermissionChoice,
    RoleCandidate,
    RoleRecord,
    Scope,
} from '@/types';

/**
 * A Role as it stands: its summary, what it allows grouped by area with each
 * Scope, and who holds it. People can be given the Role or have it taken
 * away right here, each saved at once.
 */
const props = defineProps<{
    role: RoleRecord;
    permissions: PermissionChoice[];
    /** Every Employee who can be given a Role, holding this one or not. */
    employees: RoleCandidate[];
}>();

/** Each Permission the Role holds with its Scope; Admin holds all, with Everyone. */
const granted = computed(() =>
    groupPermissions(
        props.permissions.filter(
            (permission) =>
                props.role.is_admin ||
                permission.value in props.role.permissions,
        ),
    ),
);

const grantedCount = computed(() =>
    granted.value.reduce((count, group) => count + group.permissions.length, 0),
);

function scopeOf(permission: PermissionChoice): Scope | null {
    if (permission.scopes.length === 0) {
        return null;
    }

    return props.role.is_admin
        ? 'everyone'
        : (props.role.permissions[permission.value] ?? null);
}

const search = ref('');

const holders = computed(() => {
    const query = search.value.trim().toLocaleLowerCase();

    return props.role.holders.filter((holder) =>
        holder.name.toLocaleLowerCase().includes(query),
    );
});

const candidates = computed(() =>
    props.employees.filter(
        (employee) =>
            !props.role.holders.some((holder) => holder.id === employee.id),
    ),
);

const give = useForm<{ employee_ids: number[] }>({ employee_ids: [] });

function giveTo(ids: number[]): void {
    give.employee_ids = ids;
    give.submit(RoleHolderController.store(props.role.id), {
        errorBag: `give-${props.role.id}`,
        preserveScroll: true,
    });
}

const giveError = computed(
    () =>
        Object.values(give.errors as Record<string, string>).find(Boolean) ??
        undefined,
);
</script>

<template>
    <div class="grid gap-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="grid min-w-0 gap-1">
                <h2
                    class="flex flex-wrap items-center gap-2 text-xl font-semibold"
                >
                    <span class="break-words">{{ role.name }}</span>
                    <Lock
                        v-if="role.is_admin"
                        class="size-4 text-muted-foreground"
                        aria-label="Built in"
                    />
                    <Badge v-if="role.given_to_new_employees" variant="outline"
                        >Default</Badge
                    >
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ countOf(grantedCount, 'permission', 'permissions') }} ·
                    held by
                    {{ countOf(role.holders.length, 'person', 'people') }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <Button variant="outline" class="min-h-11" as-child>
                    <Link :href="edit(role.id)" preserve-state preserve-scroll
                        >Edit</Link
                    >
                </Button>
                <ConfirmDeleteDialog
                    v-if="!role.is_admin"
                    :form="RoleController.destroy.form(role.id)"
                    :title="`Delete ${role.name}?`"
                    description="Everyone who holds it loses what it allows."
                    :error-bag="`delete-role-${role.id}`"
                >
                    <template #trigger>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-11 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                            :aria-label="`Delete ${role.name}`"
                        >
                            <Trash2 class="size-4" aria-hidden="true" />
                        </Button>
                    </template>
                </ConfirmDeleteDialog>
            </div>
        </div>

        <p
            v-if="role.is_admin || role.given_to_new_employees"
            class="flex items-start gap-2 rounded-md border bg-muted/40 px-3 py-2 text-sm"
        >
            <Info class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            {{
                role.is_admin
                    ? 'Admin always holds every Permission, with Scope Everyone.'
                    : 'Given automatically to new Employees.'
            }}
        </p>

        <Card>
            <CardHeader>
                <CardTitle>Permissions</CardTitle>
                <CardDescription
                    >What holding {{ role.name }} allows</CardDescription
                >
            </CardHeader>
            <CardContent class="grid gap-5">
                <p
                    v-if="grantedCount === 0"
                    class="text-sm text-muted-foreground"
                >
                    No permissions yet. Edit the Role to add some.
                </p>
                <section
                    v-for="group in granted"
                    :key="group.group"
                    class="grid gap-2"
                >
                    <h3
                        class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        {{ group.group }}
                    </h3>
                    <ul class="grid gap-1">
                        <li
                            v-for="permission in group.permissions"
                            :key="permission.value"
                            class="flex min-h-9 items-center justify-between gap-3 text-sm"
                        >
                            <span>{{ permission.label }}</span>
                            <ScopeBadge
                                v-if="scopeOf(permission)"
                                :scope="scopeOf(permission)!"
                            />
                        </li>
                    </ul>
                </section>
                <dl
                    v-if="grantedCount > 0"
                    class="flex flex-wrap gap-x-4 gap-y-2 border-t pt-4 text-xs text-muted-foreground"
                    aria-label="Scope key"
                >
                    <div
                        v-for="scope in scopes"
                        :key="scope.value"
                        class="flex items-center gap-1.5"
                    >
                        <dt><ScopeBadge :scope="scope.value" /></dt>
                        <dd>{{ scope.help }}</dd>
                    </div>
                </dl>
            </CardContent>
        </Card>

        <Card>
            <CardHeader
                class="flex flex-wrap items-start justify-between gap-3"
            >
                <div class="grid gap-1.5">
                    <CardTitle>People</CardTitle>
                    <CardDescription
                        >Everyone holding {{ role.name }}</CardDescription
                    >
                </div>
                <AddPeoplePopover
                    :role-name="role.name"
                    :candidates="candidates"
                    :processing="give.processing"
                    @give="giveTo"
                />
            </CardHeader>
            <CardContent class="grid gap-3">
                <InputError :message="giveError" />
                <div v-if="role.holders.length > 0" class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <Input
                        v-model="search"
                        type="search"
                        placeholder="Search people"
                        :aria-label="`Search people holding ${role.name}`"
                        class="h-11 pl-9"
                    />
                </div>
                <EmptyState
                    v-if="role.holders.length === 0"
                    :icon="Users"
                    message="Nobody holds this Role yet."
                />
                <p
                    v-else-if="holders.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    Nobody holding it matches "{{ search }}".
                </p>
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
                        <Link
                            :href="employeePage(holder.id)"
                            class="min-w-0 flex-1 truncate text-sm font-medium underline-offset-4 hover:underline"
                            >{{ holder.name }}</Link
                        >
                        <EmploymentStatusBadge
                            v-if="holder.status !== 'current'"
                            :status="holder.status"
                        />
                        <Form
                            v-bind="
                                RoleHolderController.destroy.form([
                                    role.id,
                                    holder.id,
                                ])
                            "
                            :error-bag="`take-${role.id}-${holder.id}`"
                            :options="{ preserveScroll: true }"
                            class="flex items-center gap-2"
                            v-slot="{ errors, processing }"
                        >
                            <InputError :message="errors.role" />
                            <SubmitButton
                                :processing="processing"
                                variant="ghost"
                                class="min-h-11"
                                :aria-label="`Take ${role.name} away from ${holder.name}`"
                                >Remove</SubmitButton
                            >
                        </Form>
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>
</template>
