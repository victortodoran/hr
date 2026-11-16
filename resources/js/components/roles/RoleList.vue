<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Lock, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { countOf } from '@/lib/roles';
import { show } from '@/routes/roles';
import type { RoleRecord, RolesPageMode } from '@/types';

/**
 * Every Role, searchable by name, each with how many Permissions and people
 * it has, a lock on Admin and "Default" on the Role new Employees get. While
 * a Role is edited or made, the list fades and the Role being worked on is
 * marked; a new one sits at the top with the name typed so far.
 */
const props = defineProps<{
    roles: RoleRecord[];
    selected: number | null;
    mode: RolesPageMode;
    /** How many Permissions a Role holds, Admin holding all of them. */
    permissionCount: (role: RoleRecord) => number;
    /** The name typed for a new Role, shown live at the top. */
    newName: string;
}>();

const search = ref('');

const shown = computed(() => {
    const query = search.value.trim().toLocaleLowerCase();

    return query === ''
        ? props.roles
        : props.roles.filter((role) =>
              role.name.toLocaleLowerCase().includes(query),
          );
});

const working = computed(() => props.mode !== 'view');

function summary(role: RoleRecord): string {
    const people =
        role.holders.length === 0
            ? 'Nobody yet'
            : countOf(role.holders.length, 'person', 'people');

    return `${countOf(props.permissionCount(role), 'permission', 'permissions')} · ${people}`;
}
</script>

<template>
    <Card class="gap-3 p-3">
        <div class="relative">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                aria-hidden="true"
            />
            <Input
                v-model="search"
                type="search"
                placeholder="Search roles"
                aria-label="Search roles"
                class="h-11 pl-9"
            />
        </div>

        <nav aria-label="Roles">
            <ul class="grid gap-1">
                <li v-if="mode === 'create'">
                    <div
                        class="grid min-h-11 gap-0.5 rounded-md bg-muted px-3 py-2"
                        aria-current="page"
                    >
                        <span class="truncate font-medium">{{
                            newName.trim() || 'Untitled Role'
                        }}</span>
                        <span class="text-xs text-muted-foreground"
                            >New · not saved yet</span
                        >
                    </div>
                </li>
                <li
                    v-for="role in shown"
                    :key="role.id"
                    :class="{
                        'opacity-50': working && role.id !== selected,
                    }"
                >
                    <Link
                        :href="show(role.id)"
                        preserve-state
                        preserve-scroll
                        :aria-current="
                            role.id === selected ? 'page' : undefined
                        "
                        class="grid min-h-11 gap-0.5 rounded-md px-3 py-2 hover:bg-muted/60 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        :class="{
                            'bg-muted hover:bg-muted': role.id === selected,
                        }"
                        data-test="role-item"
                    >
                        <span class="flex min-w-0 items-center gap-2">
                            <span class="truncate font-medium">{{
                                role.name
                            }}</span>
                            <Lock
                                v-if="role.is_admin"
                                class="size-3.5 shrink-0 text-muted-foreground"
                                aria-label="Built in"
                            />
                            <Badge
                                v-if="role.given_to_new_employees"
                                variant="outline"
                                >Default</Badge
                            >
                        </span>
                        <span class="text-xs text-muted-foreground">{{
                            mode === 'edit' && role.id === selected
                                ? 'Editing…'
                                : summary(role)
                        }}</span>
                    </Link>
                </li>
            </ul>
            <p
                v-if="shown.length === 0"
                class="px-3 py-2 text-sm text-muted-foreground"
            >
                No Roles match "{{ search }}".
            </p>
        </nav>
    </Card>
</template>
