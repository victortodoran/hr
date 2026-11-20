<script setup lang="ts">
import EmploymentStatusBadge from '@/components/employees/EmploymentStatusBadge.vue';
import PersonCell from '@/components/PersonCell.vue';
import { show as employeePage } from '@/routes/employees';
import type { RoleHolder } from '@/types';

defineProps<{
    holders: RoleHolder[];
    empty: string;
    linked?: boolean;
}>();

defineSlots<{
    actions: (props: { holder: RoleHolder }) => unknown;
}>();
</script>

<template>
    <ul
        v-if="holders.length > 0"
        class="divide-y rounded-lg border"
        data-test="role-holders"
    >
        <li
            v-for="holder in holders"
            :key="holder.id"
            class="flex min-h-11 items-center gap-3 px-4 py-1.5"
        >
            <PersonCell
                :name="holder.name"
                :href="linked ? employeePage(holder.id) : undefined"
                class="flex-1"
            />
            <EmploymentStatusBadge
                v-if="holder.status !== 'current'"
                :status="holder.status"
            />
            <slot name="actions" :holder="holder" />
        </li>
    </ul>
    <p v-else class="text-body text-muted-foreground">{{ empty }}</p>
</template>
