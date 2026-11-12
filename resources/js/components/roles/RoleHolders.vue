<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed } from 'vue';
import RoleHolderController from '@/actions/App/Http/Controllers/RoleHolderController';
import InputError from '@/components/InputError.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Badge } from '@/components/ui/badge';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Choice, RoleRecord } from '@/types';

const props = defineProps<{
    role: RoleRecord;
    employees: Choice[];
}>();

const candidates = computed(() =>
    props.employees.filter(
        (employee) =>
            !props.role.holders.some((holder) => holder.id === employee.id),
    ),
);
</script>

<template>
    <div class="grid gap-3">
        <p class="text-sm font-medium">Held by</p>
        <ul v-if="role.holders.length > 0" class="grid gap-2">
            <li
                v-for="holder in role.holders"
                :key="holder.id"
                class="flex items-center justify-between gap-4"
            >
                <span class="text-sm">
                    {{ holder.name }}
                    <Badge
                        v-if="holder.status !== 'current'"
                        variant="outline"
                        class="ml-2"
                        >{{
                            holder.status === 'future'
                                ? 'Future Employee'
                                : 'Former Employee'
                        }}</Badge
                    >
                </span>
                <Form
                    v-bind="
                        RoleHolderController.destroy.form([role.id, holder.id])
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
                        size="sm"
                        >Take away</SubmitButton
                    >
                </Form>
            </li>
        </ul>
        <p v-else class="text-sm text-muted-foreground">Nobody yet.</p>

        <Form
            v-if="candidates.length > 0"
            v-bind="RoleHolderController.store.form(role.id)"
            :error-bag="`give-${role.id}`"
            :options="{ preserveScroll: true }"
            reset-on-success
            class="grid gap-2"
            v-slot="{ errors, processing }"
        >
            <div class="flex gap-2">
                <Select name="employee_id" required>
                    <SelectTrigger
                        class="flex-1"
                        :aria-label="`Employee to give ${role.name} to`"
                    >
                        <SelectValue placeholder="Choose an Employee" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="employee in candidates"
                            :key="employee.id"
                            :value="String(employee.id)"
                        >
                            {{ employee.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <SubmitButton :processing="processing" variant="outline"
                    >Give</SubmitButton
                >
            </div>
            <InputError :message="errors.employee_id" />
        </Form>
    </div>
</template>
