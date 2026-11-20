<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Lock, Plus, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import DefaultRoleBadge from '@/components/roles/DefaultRoleBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { countOf } from '@/lib/roles';
import { create, show } from '@/routes/roles';
import type { RoleRecord, RolesPageMode } from '@/types';

const props = defineProps<{
    roles: RoleRecord[];
    selected: number | null;
    mode: RolesPageMode;
    permissionCount: (role: RoleRecord) => number;
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

function summary(role: RoleRecord): string {
    return `${countOf(props.permissionCount(role), 'Permission', 'Permissions')} · ${countOf(role.holders.length, 'person', 'people')}`;
}

const picked = computed({
    get: () => (props.selected === null ? 'new' : String(props.selected)),
    set: (id: string) => {
        if (id !== 'new') {
            router.visit(show(Number(id)), {
                preserveState: true,
                preserveScroll: true,
            });
        }
    },
});

const newRoleName = computed(() => props.newName.trim() || 'Untitled Role');
</script>

<template>
    <div class="flex min-w-0 flex-col gap-3">
        <div class="flex items-end gap-2 lg:hidden">
            <div class="grid min-w-0 flex-1 gap-2">
                <Label for="role-picker">Role</Label>
                <Select v-model="picked">
                    <SelectTrigger id="role-picker" class="h-11 w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-if="mode === 'create'" value="new">{{
                            newRoleName
                        }}</SelectItem>
                        <SelectItem
                            v-for="role in roles"
                            :key="role.id"
                            :value="String(role.id)"
                            >{{ role.name }}</SelectItem
                        >
                    </SelectContent>
                </Select>
            </div>
            <Button
                v-if="mode !== 'create'"
                variant="secondary"
                class="h-11"
                as-child
            >
                <Link :href="create()" preserve-state preserve-scroll
                    ><Plus />New Role</Link
                >
            </Button>
        </div>

        <div class="flex items-center gap-2 max-lg:hidden">
            <div class="relative min-w-0 flex-1">
                <Search
                    class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <Input
                    v-model="search"
                    type="search"
                    placeholder="Search"
                    aria-label="Search Roles"
                    class="h-8 pl-8"
                />
            </div>
            <Button
                v-if="mode !== 'create'"
                variant="secondary"
                size="sm"
                as-child
            >
                <Link :href="create()" preserve-state preserve-scroll
                    ><Plus />New Role</Link
                >
            </Button>
        </div>

        <nav aria-label="Roles" class="max-lg:hidden">
            <ul class="grid gap-0.5">
                <li v-if="mode === 'create'">
                    <div
                        class="grid gap-0.5 rounded-md bg-primary-soft px-3 py-2 ring-1 ring-primary-line ring-inset"
                        aria-current="page"
                    >
                        <span class="truncate font-medium">{{
                            newRoleName
                        }}</span>
                        <span class="text-caption text-muted-foreground"
                            >New · not saved yet</span
                        >
                    </div>
                </li>
                <li v-for="role in shown" :key="role.id">
                    <Link
                        :href="show(role.id)"
                        preserve-state
                        preserve-scroll
                        :aria-current="
                            role.id === selected ? 'page' : undefined
                        "
                        class="grid gap-0.5 rounded-md px-3 py-2 outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring aria-[current=page]:bg-primary-soft aria-[current=page]:ring-1 aria-[current=page]:ring-primary-line aria-[current=page]:ring-inset"
                        data-test="role-item"
                    >
                        <span class="flex min-w-0 items-center gap-1.5">
                            <span class="truncate font-medium">{{
                                role.name
                            }}</span>
                            <Lock
                                v-if="role.is_admin"
                                class="size-3.5 shrink-0 text-subtle-foreground"
                                aria-label="Built in"
                            />
                            <DefaultRoleBadge
                                v-if="role.given_to_new_employees"
                            />
                        </span>
                        <span
                            class="text-caption text-muted-foreground tabular-nums"
                            >{{ summary(role) }}</span
                        >
                    </Link>
                </li>
            </ul>
            <p
                v-if="shown.length === 0"
                class="px-3 py-2 text-body text-muted-foreground"
            >
                No Roles match "{{ search }}".
            </p>
        </nav>
    </div>
</template>
