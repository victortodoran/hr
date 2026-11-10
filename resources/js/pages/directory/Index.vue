<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index, show } from '@/routes/directory';
import type { DirectoryEntry, Paginated } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Directory', href: index() }],
    },
});

const props = defineProps<{
    employees: Paginated<DirectoryEntry>;
    search: string;
}>();

const query = ref(props.search);

const searchDirectory = useDebounceFn(() => {
    router.get(
        index.url({ query: query.value ? { search: query.value } : {} }),
        {},
        { preserveState: true, replace: true },
    );
}, 300);
</script>

<template>
    <Head title="Directory" />

    <div class="flex max-w-4xl flex-col gap-6 p-4">
        <Heading
            title="Directory"
            description="Everyone currently working at the company"
        />

        <Input
            v-model="query"
            type="search"
            placeholder="Search by name"
            aria-label="Search by name"
            class="max-w-sm"
            @update:model-value="searchDirectory"
        />

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 font-medium">Name</th>
                        <th class="px-4 py-2 font-medium">Email</th>
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
                            <a
                                :href="`mailto:${employee.email}`"
                                class="underline-offset-4 hover:underline"
                                >{{ employee.email }}</a
                            >
                        </td>
                        <td class="px-4 py-2">{{ employee.department }}</td>
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
                            No Employees match “{{ search }}”.
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
