<script setup lang="ts">
import { ref } from 'vue';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Choice, WorkEntryFilters, WorkEntryState } from '@/types';

/**
 * Filters for a list of Work Entries: an Employee, a Project, a date range
 * and, when `withState` is set, a state. Emits the query string to reload
 * the list with whenever one changes.
 */
const props = defineProps<{
    filters: WorkEntryFilters & { state?: WorkEntryState | null };
    employees: Choice[];
    projects: Choice[];
    /** Whether to offer a state filter, with "Any state" for none. */
    withState?: boolean;
}>();

const emit = defineEmits<{
    change: [query: Record<string, string>];
}>();

const states: { value: WorkEntryState; label: string }[] = [
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'locked', label: 'Locked' },
];

const employee = ref(props.filters.employee?.toString() ?? 'all');
const project = ref(props.filters.project?.toString() ?? 'all');
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');
const state = ref<string>(props.filters.state ?? 'all');

function changed(): void {
    emit('change', {
        ...(employee.value !== 'all' ? { employee: employee.value } : {}),
        ...(project.value !== 'all' ? { project: project.value } : {}),
        ...(from.value ? { from: from.value } : {}),
        ...(to.value ? { to: to.value } : {}),
        ...(props.withState && state.value !== 'all'
            ? { state: state.value }
            : {}),
    });
}
</script>

<template>
    <div class="flex flex-wrap items-end gap-3">
        <Select v-model="employee" @update:model-value="changed">
            <SelectTrigger class="w-48" aria-label="Employee">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="all">Any Employee</SelectItem>
                <SelectItem
                    v-for="choice in employees"
                    :key="choice.id"
                    :value="String(choice.id)"
                    >{{ choice.name }}</SelectItem
                >
            </SelectContent>
        </Select>
        <Select v-model="project" @update:model-value="changed">
            <SelectTrigger class="w-48" aria-label="Project">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="all">Any Project</SelectItem>
                <SelectItem
                    v-for="choice in projects"
                    :key="choice.id"
                    :value="String(choice.id)"
                    >{{ choice.name }}</SelectItem
                >
            </SelectContent>
        </Select>
        <Input
            v-model="from"
            type="date"
            class="w-40"
            aria-label="From"
            @change="changed"
        />
        <Input
            v-model="to"
            type="date"
            class="w-40"
            aria-label="To"
            @change="changed"
        />
        <Select v-if="withState" v-model="state" @update:model-value="changed">
            <SelectTrigger class="w-36" aria-label="State">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="all">Any state</SelectItem>
                <SelectItem
                    v-for="choice in states"
                    :key="choice.value"
                    :value="choice.value"
                    >{{ choice.label }}</SelectItem
                >
            </SelectContent>
        </Select>
    </div>
</template>
