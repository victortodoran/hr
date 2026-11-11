<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import EmploymentStatusBadge from '@/components/employees/EmploymentStatusBadge.vue';
import { show as directoryPage } from '@/routes/directory';
import { show as employeePage } from '@/routes/employees';
import type { ChainMember } from '@/types';

const props = defineProps<{
    member: ChainMember;
    /** Whether this is the Employee whose Reporting Chain is shown. */
    isViewed?: boolean;
}>();

/**
 * Where the member's name leads, or null when it leads nowhere: the viewed
 * Employee's own card, or a member the viewer may follow nowhere.
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
    <component
        :is="href ? Link : 'div'"
        :href="href ?? undefined"
        :aria-current="isViewed ? 'page' : undefined"
        class="block w-full max-w-xs rounded-lg border px-4 py-3 text-center"
        :class="
            isViewed
                ? 'border-primary bg-primary/5 ring-1 ring-primary'
                : href
                  ? 'transition-colors hover:bg-accent'
                  : ''
        "
    >
        <p class="font-medium">{{ member.name }}</p>
        <p v-if="member.job_title" class="text-sm text-muted-foreground">
            {{ member.job_title }}
        </p>
        <p v-if="member.department" class="text-xs text-muted-foreground">
            {{ member.department }}
        </p>
        <EmploymentStatusBadge
            v-if="member.status === 'future'"
            :status="member.status"
            class="mt-1"
        />
    </component>
</template>
