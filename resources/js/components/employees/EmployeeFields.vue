<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Choice, EmployeeRecord, ManagerChoice } from '@/types';

defineProps<{
    departments: Choice[];
    managers: ManagerChoice[];
    errors: Record<string, string | undefined>;
    employee?: EmployeeRecord;
}>();
</script>

<template>
    <div class="grid gap-6">
        <div class="grid gap-2">
            <Label for="name">Full name</Label>
            <Input
                id="name"
                name="name"
                :default-value="employee?.name"
                required
                autocomplete="off"
            />
            <InputError :message="errors.name" />
        </div>

        <div class="grid gap-2">
            <Label for="email">Email</Label>
            <Input
                id="email"
                type="email"
                name="email"
                :default-value="employee?.email"
                required
                autocomplete="off"
            />
            <InputError :message="errors.email" />
        </div>

        <div class="grid gap-2">
            <Label for="department_id">Department</Label>
            <Select
                name="department_id"
                :default-value="
                    employee ? String(employee.department_id) : undefined
                "
                required
            >
                <SelectTrigger id="department_id" class="w-full">
                    <SelectValue placeholder="Choose a Department" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="department in departments"
                        :key="department.id"
                        :value="String(department.id)"
                    >
                        {{ department.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <InputError :message="errors.department_id" />
        </div>

        <div v-if="!employee?.is_top" class="grid gap-2">
            <Label for="manager_id">Manager</Label>
            <Select
                name="manager_id"
                :default-value="
                    employee?.manager_id
                        ? String(employee.manager_id)
                        : undefined
                "
                required
            >
                <SelectTrigger id="manager_id" class="w-full">
                    <SelectValue placeholder="Choose a Manager" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="manager in managers"
                        :key="manager.id"
                        :value="String(manager.id)"
                    >
                        {{ manager.name }}
                        <span
                            v-if="manager.status === 'future'"
                            class="text-muted-foreground"
                        >
                            (Future Employee)
                        </span>
                    </SelectItem>
                </SelectContent>
            </Select>
            <InputError :message="errors.manager_id" />
        </div>
        <p v-else class="text-sm text-muted-foreground">
            This is the top Employee, so they have no Manager.
        </p>

        <div class="grid gap-2">
            <Label for="start_date">Start Date</Label>
            <Input
                id="start_date"
                type="date"
                name="start_date"
                :default-value="employee?.start_date"
                required
            />
            <InputError :message="errors.start_date" />
        </div>

        <div class="grid gap-2">
            <Label for="leaving_date">Leaving Date</Label>
            <template v-if="employee?.is_top">
                <p class="text-sm text-muted-foreground">
                    Name a new top Employee before the current one can leave.
                </p>
            </template>
            <template v-else>
                <Input
                    id="leaving_date"
                    type="date"
                    name="leaving_date"
                    :default-value="employee?.leaving_date ?? undefined"
                    :readonly="employee?.status === 'former'"
                />
                <p
                    v-if="employee?.status === 'former'"
                    class="text-sm text-muted-foreground"
                >
                    This Leaving Date has passed, so it can't be changed.
                </p>
                <p v-else class="text-sm text-muted-foreground">
                    Optional. Their last working day; they can't sign in after
                    it.
                </p>
            </template>
            <InputError :message="errors.leaving_date" />
        </div>
    </div>
</template>
