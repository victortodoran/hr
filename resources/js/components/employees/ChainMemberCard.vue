<script setup lang="ts">
import { computed } from 'vue';
import EmploymentStatusBadge from '@/components/employees/EmploymentStatusBadge.vue';
import PersonCell from '@/components/PersonCell.vue';
import { index as directory } from '@/routes/directory';
import { show as employeePage } from '@/routes/employees';
import type { ChainMember } from '@/types';

/** The viewed Employee's status is already in the page header, so only others say they're Future. */
const props = defineProps<{
    member: ChainMember;
    isViewed?: boolean;
}>();

const href = computed(() => {
    if (props.isViewed) {
        return null;
    }

    if (props.member.link === 'employee') {
        return employeePage(props.member.id);
    }

    return props.member.link === 'directory'
        ? directory({ query: { person: props.member.id } })
        : null;
});
</script>

<template>
    <div
        :aria-current="isViewed ? 'page' : undefined"
        class="flex min-h-11 items-center gap-3 rounded-md px-2 py-1.5"
        :class="isViewed ? 'bg-muted font-medium' : ''"
    >
        <PersonCell
            :name="member.name"
            :detail="member.job_title"
            :href="href ?? undefined"
            class="flex-1"
        />
        <EmploymentStatusBadge
            v-if="!isViewed && member.status === 'future'"
            :status="member.status"
            short
        />
    </div>
</template>
