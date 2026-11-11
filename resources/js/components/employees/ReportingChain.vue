<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import EmploymentStatusBadge from '@/components/employees/EmploymentStatusBadge.vue';
import { show as directoryPage } from '@/routes/directory';
import { show as employeePage } from '@/routes/employees';
import type { ChainMember, ReportingChainView } from '@/types';

defineProps<{
    chain: ReportingChainView;
}>();

/**
 * Where a member's name leads, or null when the viewer may follow it
 * nowhere.
 */
function hrefOf(member: ChainMember) {
    if (member.link === 'employee') {
        return employeePage(member.id);
    }

    return member.link === 'directory' ? directoryPage(member.id) : null;
}
</script>

<template>
    <div class="flex flex-col items-center" aria-label="Reporting Chain">
        <ol class="flex w-full flex-col items-center">
            <li
                v-for="member in [...chain.above, chain.employee]"
                :key="member.id"
                class="flex w-full flex-col items-center"
            >
                <component
                    :is="
                        member.id !== chain.employee.id && hrefOf(member)
                            ? Link
                            : 'div'
                    "
                    :href="
                        member.id !== chain.employee.id
                            ? (hrefOf(member) ?? undefined)
                            : undefined
                    "
                    :aria-current="
                        member.id === chain.employee.id ? 'page' : undefined
                    "
                    class="w-full max-w-xs rounded-lg border px-4 py-3 text-center"
                    :class="
                        member.id === chain.employee.id
                            ? 'border-primary bg-primary/5 ring-1 ring-primary'
                            : hrefOf(member)
                              ? 'transition-colors hover:bg-accent'
                              : ''
                    "
                >
                    <p class="font-medium">{{ member.name }}</p>
                    <p
                        v-if="member.job_title"
                        class="text-sm text-muted-foreground"
                    >
                        {{ member.job_title }}
                    </p>
                    <p
                        v-if="member.department"
                        class="text-xs text-muted-foreground"
                    >
                        {{ member.department }}
                    </p>
                    <EmploymentStatusBadge
                        v-if="member.status === 'future'"
                        :status="member.status"
                        class="mt-1"
                    />
                </component>
                <div
                    v-if="member.id !== chain.employee.id || chain.below.length"
                    class="h-6 w-px bg-border"
                    aria-hidden="true"
                />
            </li>
        </ol>

        <ul
            v-if="chain.below.length > 0"
            class="grid w-full gap-2 border-t pt-4 sm:grid-cols-2"
            aria-label="Employees they are the Manager of"
        >
            <li v-for="member in chain.below" :key="member.id">
                <component
                    :is="hrefOf(member) ? Link : 'div'"
                    :href="hrefOf(member) ?? undefined"
                    class="block rounded-lg border px-4 py-3 text-center"
                    :class="
                        hrefOf(member)
                            ? 'transition-colors hover:bg-accent'
                            : ''
                    "
                >
                    <p class="font-medium">{{ member.name }}</p>
                    <p
                        v-if="member.job_title"
                        class="text-sm text-muted-foreground"
                    >
                        {{ member.job_title }}
                    </p>
                    <p
                        v-if="member.department"
                        class="text-xs text-muted-foreground"
                    >
                        {{ member.department }}
                    </p>
                    <EmploymentStatusBadge
                        v-if="member.status === 'future'"
                        :status="member.status"
                        class="mt-1"
                    />
                </component>
            </li>
        </ul>
    </div>
</template>
