<script setup lang="ts">
import { computed } from 'vue';
import EmploymentStatusBadge from '@/components/employees/EmploymentStatusBadge.vue';
import PersonCell from '@/components/PersonCell.vue';
import { show as directoryPage } from '@/routes/directory';
import { show as employeePage } from '@/routes/employees';
import type { ChainMember } from '@/types';

/**
 * One person in a Reporting Chain, on one compact line: a PersonCell with
 * their initials, name and Job Title. The viewed Employee is marked rather than linked; a
 * Future Employee elsewhere in the chain says so, while the viewed one's
 * status is already in the page header.
 */
const props = defineProps<{
    member: ChainMember;
    /** Whether this is the Employee whose Reporting Chain is shown. */
    isViewed?: boolean;
}>();

/**
 * Where the member's name leads: their Employee page or their Directory
 * page. Null on the viewed Employee's own line, and when the viewer may open
 * neither page (`link` is null).
 */
const href = computed(() => {
    if (props.isViewed) {
        return null;
    }

    if (props.member.link === 'employee') {
        return employeePage(props.member.id);
    }

    return props.member.link === 'directory'
        ? directoryPage(props.member.id)
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
