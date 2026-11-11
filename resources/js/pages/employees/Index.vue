<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import ValueOr from '@/components/ValueOr.vue';
import { create, index, show } from '@/routes/employees';
import type { EmployeeListEntry, EmploymentStatus, Paginated } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Employees', href: index() }],
    },
});

const props = defineProps<{
    employees: Paginated<EmployeeListEntry>;
    search: string;
    status: EmploymentStatus | null;
    canAdd: boolean;
}>();

const statusBadges = {
    future: { label: 'Future', variant: 'secondary' },
    current: { label: 'Current', variant: 'default' },
    former: { label: 'Former', variant: 'outline' },
} as const;

const query = ref(props.search);
const statusFilter = ref<string>(props.status ?? 'all');

/**
 * Reload the list with the current search and status filter.
 */
function filter(): void {
    router.get(
        index.url({
            query: {
                ...(query.value ? { search: query.value } : {}),
                ...(statusFilter.value !== 'all'
                    ? { status: statusFilter.value }
                    : {}),
            },
        }),
        {},
        { preserveState: true, replace: true },
    );
}

const searchEmployees = useDebounceFn(filter, 300);
</script>

<template>
    <Head title="Employees" />

    <div class="flex max-w-4xl flex-col gap-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                title="Employees"
                description="Everyone whose record you may see: future, current and former"
            />
            <Button v-if="canAdd" as-child>
                <Link :href="create()">Add Employee</Link>
            </Button>
        </div>

        <div class="flex flex-wrap gap-3">
            <Input
                v-model="query"
                type="search"
                placeholder="Search by name"
                aria-label="Search by name"
                class="max-w-sm"
                @update:model-value="searchEmployees"
            />
            <Select v-model="statusFilter" @update:model-value="filter">
                <SelectTrigger class="w-44" aria-label="Status">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Any status</SelectItem>
                    <SelectItem value="future">Future</SelectItem>
                    <SelectItem value="current">Current</SelectItem>
                    <SelectItem value="former">Former</SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 font-medium">Name</th>
                        <th class="px-4 py-2 font-medium">Status</th>
                        <th class="px-4 py-2 font-medium">Department</th>
                        <th class="px-4 py-2 font-medium">Manager</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="employee in employees.data" :key="employee.id">
                        <td class="px-4 py-2 font-medium">
                            <Link
                                :href="show(employee.id)"
                                class="underline-offset-4 hover:underline"
                                >{{ employee.name }}</Link
                            >
                        </td>
                        <td class="px-4 py-2">
                            <Badge
                                :variant="statusBadges[employee.status].variant"
                            >
                                {{ statusBadges[employee.status].label }}
                            </Badge>
                        </td>
                        <td class="px-4 py-2">
                            <ValueOr :value="employee.department" />
                        </td>
                        <td class="px-4 py-2">
                            <Link
                                v-if="employee.manager"
                                :href="show(employee.manager.id)"
                                class="underline-offset-4 hover:underline"
                                >{{ employee.manager.name }}</Link
                            >
                            <span v-else class="text-muted-foreground"
                                >None (top Employee)</span
                            >
                        </td>
                    </tr>
                    <tr v-if="employees.data.length === 0">
                        <td
                            colspan="4"
                            class="px-4 py-6 text-center text-muted-foreground"
                        >
                            No Employees match.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="employees.prev_page_url || employees.next_page_url"
            class="flex items-center justify-between gap-4 text-sm text-muted-foreground"
        >
            <span>
                {{ employees.from }}–{{ employees.to }} of
                {{ employees.total }}
            </span>
            <div class="flex gap-2">
                <Button
                    v-for="pageLink in [
                        { label: 'Previous', url: employees.prev_page_url },
                        { label: 'Next', url: employees.next_page_url },
                    ]"
                    :key="pageLink.label"
                    variant="outline"
                    size="sm"
                    :disabled="!pageLink.url"
                    :as="pageLink.url ? Link : 'button'"
                    :href="pageLink.url ?? undefined"
                >
                    {{ pageLink.label }}
                </Button>
            </div>
        </div>
    </div>
</template>
